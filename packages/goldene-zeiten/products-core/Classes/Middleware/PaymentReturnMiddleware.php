<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Middleware;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Payment\CheckoutMessageQueue;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Core\Payment\PaymentCallbackService;
use GoldeneZeiten\Products\Core\Payment\PaymentUrlFactory;
use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Where a payment gateway sends the customer back to after they approved or abandoned the payment.
 *
 * It is a middleware on a fixed path, not a plugin action, for the same reason the webhook is
 * {@see PaymentWebhookMiddleware}: a gateway appends its own query parameters to the return URL
 * (`?session_id=…`, `?PayerID=…`), which a cHash-validated Extbase route rejects outright. Finalizing
 * here - before page resolution - sidesteps cHash entirely, and the order is proven by its signed token
 * rather than the (now irrelevant) request parameters. Only the clean redirect that follows is routed.
 *
 * A declined or abandoned payment sends the customer back to the payment step with a message instead of the
 * thank-you page; the order is cancelled and the basket kept for another attempt.
 */
final readonly class PaymentReturnMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PaymentCallbackService $paymentCallbackService,
        private PaymentUrlFactory $paymentUrlFactory,
        private OrderTokenService $orderTokenService,
        private CheckoutMessageQueue $checkoutMessageQueue,
        private LanguageServiceFactory $languageServiceFactory
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $isReturn = $path === PaymentUrlFactory::RETURN_PATH;
        $site = $request->getAttribute('site');
        if ((!$isReturn && $path !== PaymentUrlFactory::CANCEL_PATH) || !$site instanceof Site) {
            return $handler->handle($request);
        }

        $queryParams = $request->getQueryParams();
        $orderUid = (int)($queryParams[PaymentUrlFactory::ORDER_PARAM] ?? 0);
        $token = (string)($queryParams[PaymentUrlFactory::SIGNATURE_PARAM] ?? '');

        if (!$isReturn) {
            return $this->cancel($site, $orderUid, $token, $request);
        }

        return $this->finalize($site, $orderUid, $token, $request);
    }

    private function finalize(Site $site, int $orderUid, string $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $result = $this->paymentCallbackService->handleReturn($orderUid, $token, $request);
        } catch (PaymentCallbackException | OrderPlacementExceptionInterface) {
            return new RedirectResponse($this->paymentUrlFactory->checkoutStepUrl($site, 'payment'));
        }

        // A multi-hop gateway (Amazon Pay) asks for one more redirect back to itself before it settles.
        if ($result->getState() === PaymentResultState::REDIRECT_REQUIRED) {
            return new RedirectResponse($result->getRedirectUrl());
        }

        // The thank-you page gates on the order token, so hand it the one for this order - the token proves
        // the caller just settled this order rather than guessing a neighbouring order's uid.
        $order = $this->paymentCallbackService->resolveOrder($orderUid, $token);
        if ($result->getState() === PaymentResultState::FAILED || $order->getStatus() === OrderStatus::CANCELLED) {
            return $this->backToPayment($site, $request, 'checkout_payment_failed');
        }

        return new RedirectResponse(
            $this->paymentUrlFactory->checkoutStepUrl($site, 'thankYou', [
                'order' => $orderUid,
                'hash' => $this->orderTokenService->generateToken($order),
            ])
        );
    }

    /**
     * Resolving the order first rejects a forged token, so an outsider cannot cancel someone else's order.
     */
    private function cancel(Site $site, int $orderUid, string $token, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->paymentCallbackService->handleCancel($orderUid, $token);
        } catch (PaymentCallbackException) {
            // A tampered cancel link is not worth an error page; the checkout will re-challenge the order.
            return new RedirectResponse($this->paymentUrlFactory->checkoutStepUrl($site, 'payment'));
        }

        return $this->backToPayment($site, $request, 'checkout_payment_cancelled');
    }

    private function backToPayment(Site $site, ServerRequestInterface $request, string $messageKey): ResponseInterface
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $language = $request->getAttribute('language');
            $languageService = $this->languageServiceFactory->createFromSiteLanguage(
                $language instanceof SiteLanguage ? $language : $site->getDefaultLanguage()
            );
            $this->checkoutMessageQueue->addError(
                $frontendUser,
                $languageService->sL('LLL:EXT:products_core/Resources/Private/Language/locallang.xlf:' . $messageKey)
            );
        }

        return new RedirectResponse($this->paymentUrlFactory->checkoutStepUrl($site, 'payment'));
    }
}
