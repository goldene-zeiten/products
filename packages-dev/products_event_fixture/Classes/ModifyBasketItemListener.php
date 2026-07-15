<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\ModifyBasketItemEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class ModifyBasketItemListener
{
    public static int $invocationCount = 0;

    public function __invoke(ModifyBasketItemEvent $event): void
    {
        self::$invocationCount++;
        $viewItem = $event->getViewItem();
        $modified = new BasketViewItem(
            $viewItem->getProduct(),
            $viewItem->getArticle(),
            $viewItem->getQuantity(),
            $viewItem->getUnitPriceNet(),
            Money::fromCents(4242),
            $viewItem->getTaxRate(),
            $viewItem->getLineTotalNet(),
            $viewItem->getLineTotalGross(),
            $viewItem->getLineTotalTax()
        );
        $event->setViewItem($modified);
    }
}
