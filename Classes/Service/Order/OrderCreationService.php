<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderCreationService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly OrderRepository $orderRepository,
        private readonly OrderFactory $orderFactory,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function create(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): Order {
        $this->decrementStock($basketViewModel);

        $order = $this->orderFactory->create($request, $basketViewModel, $address, $paymentMethod->getIdentifier());
        $this->orderRepository->add($order);
        $this->persistenceManager->persistAll();

        $this->eventDispatcher->dispatch(new AfterOrderPlacedEvent($order, $request));

        return $order;
    }

    private function decrementStock(BasketViewModel $basketViewModel): void
    {
        foreach ($basketViewModel->getItems() as $viewItem) {
            $this->stockService->decrementForItem(
                $viewItem->getProduct()->getUid() ?? 0,
                $viewItem->getArticle()?->getUid(),
                $viewItem->getQuantity()
            );
        }
    }
}
