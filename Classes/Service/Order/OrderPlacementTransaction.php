<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\DiscountRequest;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class OrderPlacementTransaction
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OrderCreationService $orderCreationService,
        private readonly PaymentInitiationService $paymentInitiationService
    ) {}

    /**
     * @return array{0: Order, 1: PaymentResult}
     */
    public function run(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        DiscountRequest $discountRequest,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): array {
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_order');
        $connection->beginTransaction();
        try {
            $order = $this->orderCreationService->create($request, $basketViewModel, $discountRequest, $address, $paymentMethod);
            $paymentResult = $this->paymentInitiationService->initiate($order, $paymentMethod);
            $connection->commit();
            return [$order, $paymentResult];
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
