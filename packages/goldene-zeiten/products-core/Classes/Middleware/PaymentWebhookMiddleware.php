<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Middleware;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Core\Payment\PaymentCallbackService;
use GoldeneZeiten\Products\Core\Payment\PaymentUrlFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * The endpoint a payment gateway posts its asynchronous confirmation to.
 *
 * It is a middleware rather than a plugin action because the gateway is not a browser: it has no session,
 * follows no redirects and must not be handed a rendered page. A fixed path also means the URL a gateway
 * was given at payment time keeps working regardless of what happens to the page tree.
 */
final readonly class PaymentWebhookMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PaymentCallbackService $paymentCallbackService,
        private LoggerInterface $logger
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path === PaymentUrlFactory::WEBHOOK_PATH) {
            $queryParams = $request->getQueryParams();
            $orderUid = (int)($queryParams[PaymentUrlFactory::ORDER_PARAM] ?? 0);
            $token = (string)($queryParams[PaymentUrlFactory::SIGNATURE_PARAM] ?? '');

            return $this->respond(fn(): PaymentResult => $this->paymentCallbackService->handleWebhook($orderUid, $token, $request));
        }

        $gateway = $this->staticGateway($path);
        if ($gateway !== null) {
            return $this->respond(fn(): PaymentResult => $this->paymentCallbackService->handleStaticWebhook($gateway, $request));
        }

        return $handler->handle($request);
    }

    /**
     * @param callable(): PaymentResult $handle
     */
    private function respond(callable $handle): ResponseInterface
    {
        try {
            $paymentResult = $handle();
        } catch (PaymentCallbackException $exception) {
            // The caller is a gateway, not a browser: keep the reason in the log and never confirm which
            // order uids exist by echoing the resolution failure back.
            $this->logger->warning('Rejected a payment webhook.', ['exception' => $exception]);
            return new JsonResponse(['error' => 'Invalid payment callback.'], 404);
        }

        return new JsonResponse(['status' => $paymentResult->getPaymentStatus()->value]);
    }

    private function staticGateway(string $path): ?string
    {
        $prefix = PaymentUrlFactory::WEBHOOK_PATH . '/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }
        $gateway = substr($path, strlen($prefix));

        return $gateway !== '' && !str_contains($gateway, '/') ? $gateway : null;
    }
}
