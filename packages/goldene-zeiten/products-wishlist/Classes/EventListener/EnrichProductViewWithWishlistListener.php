<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\EventListener;

use GoldeneZeiten\Products\Core\Event\EnrichProductViewEvent;
use GoldeneZeiten\Products\Wishlist\Service\WishlistService;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Tells the core catalog list and detail views whether the wishlist is enabled, so the wishlist toggle
 * partial this extension overrides into those views knows whether to render the toggle. With this extension
 * absent the core dispatches the event to no one.
 *
 * Nothing per visitor is added: the list view is cached for everyone.
 */
final readonly class EnrichProductViewWithWishlistListener
{
    public function __construct(
        private WishlistService $wishlistService,
    ) {}

    #[AsEventListener]
    public function __invoke(EnrichProductViewEvent $event): void
    {
        $event->addVariable('wishlistEnabled', $this->wishlistService->isEnabled($event->getRequest()));
    }
}
