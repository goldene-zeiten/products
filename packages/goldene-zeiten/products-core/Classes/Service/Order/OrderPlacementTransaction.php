<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Places an order and starts its payment. The order with its stock, number and loyalty bookings is created
 * atomically; the payment - usually a remote gateway call - is started only once that is committed, so the
 * row locks are not held across it. An order whose payment cannot be started is cancelled, which returns
 * its stock.
 */
final class OrderPlacementTransaction
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OrderCreationService $orderCreationService,
        private readonly PaymentInitiationService $paymentInitiationService,
        private readonly OrderFinalizationService $orderFinalizationService
    ) {}

    /**
     * @return array{0: Order, 1: PaymentResult}
     */
    public function run(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): array {
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_order');
        $connection->beginTransaction();
        try {
            $order = $this->orderCreationService->create($request, $basketViewModel, $checkoutSelections, $address, $paymentMethod);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        try {
            $paymentResult = $this->paymentInitiationService->initiate($order, $paymentMethod, $request);
        } catch (\Throwable $exception) {
            $this->orderFinalizationService->reject($order, 'The payment could not be started.');
            throw $exception;
        }

        return [$order, $paymentResult];
    }
}
