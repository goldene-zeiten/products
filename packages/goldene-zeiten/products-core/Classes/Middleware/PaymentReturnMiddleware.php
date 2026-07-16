<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Middleware;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Core\Payment\PaymentCallbackService;
use GoldeneZeiten\Products\Core\Payment\PaymentUrlFactory;
use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Where a payment gateway sends the customer back to after they approved or abandoned the payment.
 *
 * It is a middleware on a fixed path, not a plugin action, for the same reason the webhook is
 * {@see PaymentWebhookMiddleware}: a gateway appends its own query parameters to the return URL
 * (`?session_id=…`, `?PayerID=…`), which a cHash-validated Extbase route rejects outright. Finalizing
 * here - before page resolution - sidesteps cHash entirely, and the order is proven by its signed token
 * rather than the (now irrelevant) request parameters. Only the clean redirect that follows is routed.
 */
final readonly class PaymentReturnMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PaymentCallbackService $paymentCallbackService,
        private PaymentUrlFactory $paymentUrlFactory
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
            return $this->cancel($site, $orderUid, $token);
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

        return new RedirectResponse(
            $this->paymentUrlFactory->checkoutStepUrl($site, 'thankYou', ['order' => $orderUid])
        );
    }

    /**
     * An abandoned payment leaves the order untouched so the customer can pick another method; resolving
     * it first still rejects a forged token before we send anyone back into the checkout.
     */
    private function cancel(Site $site, int $orderUid, string $token): ResponseInterface
    {
        try {
            $this->paymentCallbackService->resolveOrder($orderUid, $token);
        } catch (PaymentCallbackException) {
            // A tampered cancel link is not worth an error page; the checkout will re-challenge the order.
        }

        return new RedirectResponse($this->paymentUrlFactory->checkoutStepUrl($site, 'payment'));
    }
}
