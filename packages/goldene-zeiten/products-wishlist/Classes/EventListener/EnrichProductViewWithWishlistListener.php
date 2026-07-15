<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\EventListener;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Event\EnrichProductViewEvent;
use GoldeneZeiten\Products\Wishlist\Service\WishlistService;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Adds the wishlist state to the core catalog list and detail views, so the wishlist toggle partial this
 * extension overrides into those views knows whether the wishlist is enabled and which products are on it.
 * With this extension absent the core dispatches the event to no one and the views carry no wishlist state.
 */
final readonly class EnrichProductViewWithWishlistListener
{
    public function __construct(
        private WishlistService $wishlistService,
    ) {}

    #[AsEventListener]
    public function __invoke(EnrichProductViewEvent $event): void
    {
        $enabled = $this->wishlistService->isEnabled($event->getRequest());
        $productUids = $enabled
            ? array_map(
                static fn(Product $product): int => $product->getUid() ?? 0,
                $this->wishlistService->getItems($event->getRequest()),
            )
            : [];

        $event->addVariable('wishlistEnabled', $enabled);
        $event->addVariable('wishlistProductUids', $productUids);
        $event->addVariable('wishlistCount', count($productUids));
    }
}
