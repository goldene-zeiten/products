<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * A fixture gateway that refuses to start every payment. It records whether the placement still held its
 * database transaction open while "calling" the gateway, so a test can prove the remote call does not hold
 * the stock and number-range row locks.
 */
final class DecliningGatewayPaymentMethod implements PaymentMethodInterface
{
    public static ?bool $transactionActiveDuringInitiate = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    public function getIdentifier(): string
    {
        return 'fixture-declining';
    }

    public function getLabel(): string
    {
        return 'Declining Gateway Fixture Payment';
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
        self::$transactionActiveDuringInitiate = $this->connectionPool
            ->getConnectionForTable('tx_products_domain_model_order')
            ->isTransactionActive();

        return PaymentResult::failed('The fixture gateway refused to start the payment.');
    }

    public static function reset(): void
    {
        self::$transactionActiveDuringInitiate = null;
    }
}
