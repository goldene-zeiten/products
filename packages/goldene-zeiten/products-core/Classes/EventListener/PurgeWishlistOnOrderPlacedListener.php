<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\EventListener;

use GoldeneZeiten\Products\Core\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Core\Service\Wishlist\WishlistService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * A wishlist cleanup failure must never roll back the order placement.
 */
#[AsEventListener]
final class PurgeWishlistOnOrderPlacedListener
{
    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(AfterOrderPlacedEvent $event): void
    {
        try {
            $this->wishlistService->removeOrderedItems($event->getOrder());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to purge wishlist items for order %d.', $event->getOrder()->getUid() ?? 0),
                ['exception' => $exception]
            );
        }
    }
}
