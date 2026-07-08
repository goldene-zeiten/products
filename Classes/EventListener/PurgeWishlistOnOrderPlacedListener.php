<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Removing ordered items from the wishlist is a convenience cleanup, not a condition for a
 * successful order - a failure here must never roll back the placement, same reasoning as
 * SendOrderEmailsListener never failing the request over a mail error.
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
