<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fixture redirect payment method for testing callback handlers. A callback reports a paid payment unless
 * it carries `outcome=failed` or `outcome=pending`, standing in for what a real gateway would report.
 */
final class RedirectGatewayPaymentMethod implements RedirectPaymentMethodInterface
{
    /**
     * @var array<int, array<string, string>>
     */
    public static array $seenContext = [];

    public function getIdentifier(): string
    {
        return 'fixture-redirect';
    }

    public function getLabel(): string
    {
        return 'Redirect Gateway Fixture Payment';
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
        // Record the callback URLs for test inspection
        self::$seenContext[(int)$order->getUid()] = [
            'return' => $context->getReturnUrl(),
            'cancel' => $context->getCancelUrl(),
            'webhook' => $context->getWebhookUrl(),
        ];

        return PaymentResult::redirectRequired('https://gateway.example/pay/123', 'FIXTURE-REDIRECT');
    }

    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
    {
        return $this->outcome($request, 'FIXTURE-RETURN');
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        return $this->outcome($request, 'FIXTURE-WEBHOOK');
    }

    private function outcome(ServerRequestInterface $request, string $externalId): PaymentResult
    {
        return match ($request->getQueryParams()['outcome'] ?? '') {
            'failed' => PaymentResult::failed('The fixture gateway declined the payment.', $externalId),
            'pending' => PaymentResult::pending($externalId),
            default => PaymentResult::completed(PaymentStatus::PAID, $externalId),
        };
    }

    public static function reset(): void
    {
        self::$seenContext = [];
    }
}
