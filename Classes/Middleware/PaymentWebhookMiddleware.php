<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Middleware;

use GoldeneZeiten\Products\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Payment\PaymentCallbackService;
use GoldeneZeiten\Products\Payment\PaymentUrlFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
        private PaymentCallbackService $paymentCallbackService
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== PaymentUrlFactory::WEBHOOK_PATH) {
            return $handler->handle($request);
        }

        $queryParams = $request->getQueryParams();
        $orderUid = (int)($queryParams['order'] ?? 0);
        $token = (string)($queryParams['token'] ?? '');

        try {
            $paymentResult = $this->paymentCallbackService->handleWebhook($orderUid, $token, $request);
        } catch (PaymentCallbackException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['status' => $paymentResult->getPaymentStatus()->value]);
    }
}
