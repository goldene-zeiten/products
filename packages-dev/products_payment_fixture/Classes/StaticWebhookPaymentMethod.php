<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\WebhookResolution;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\StaticWebhookPaymentMethodInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A fixture gateway with a static, dashboard-style webhook: the callback carries no per-order token, so the
 * method resolves the order from the payload itself. A plain `order` query parameter stands in for the
 * signed gateway body a real gateway would send, so the test can exercise PaymentWebhookMiddleware's
 * static-webhook routing without real signature crypto.
 */
final class StaticWebhookPaymentMethod implements RedirectPaymentMethodInterface, StaticWebhookPaymentMethodInterface
{
    public function __construct(
        private readonly OrderRepository $orderRepository
    ) {}

    public function getIdentifier(): string
    {
        return 'fixture-static-webhook';
    }

    public function getLabel(): string
    {
        return 'Static Webhook Fixture Payment';
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return true;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::redirectRequired('https://gateway.example/approve', 'FIXTURE-STATIC');
    }

    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
    {
        return PaymentResult::pending('FIXTURE-STATIC');
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PAID, 'FIXTURE-STATIC');
    }

    public function handleStaticWebhook(ServerRequestInterface $request): WebhookResolution
    {
        $order = $this->orderRepository->findByUid((int)($request->getQueryParams()['order'] ?? 0));
        if ($order === null) {
            return WebhookResolution::unresolved(PaymentResult::failed('The webhook references an unknown order.'));
        }

        return WebhookResolution::resolved($order, PaymentResult::completed(PaymentStatus::PAID, 'FIXTURE-STATIC-WEBHOOK'));
    }
}
