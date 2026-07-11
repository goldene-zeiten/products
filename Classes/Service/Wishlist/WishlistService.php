<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Wishlist;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Model\WishlistItem;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\WishlistItemRepository;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Picks a persisted (tx_products_domain_model_wishlistitem) or FE-session backend by login state.
 * A guest's session wishlist is merged into the account's persisted wishlist on login (see
 * mergeSessionIntoAccount(), invoked by MergeWishlistOnLoginListener) - legacy always did this via
 * tx_ttproducts_control_memo::copySession2Feuser(), so this is parity, not a new feature.
 */
final class WishlistService
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly WishlistItemRepository $wishlistItemRepository,
        private readonly WishlistStorage $wishlistStorage,
        private readonly ProductRepository $productRepository,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly PersistenceManagerInterface $persistenceManager,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    /**
     * Gates only the "add/remove to wishlist" affordance on product list/detail templates - the
     * plugin/controller itself works regardless, opt-in by placing it on a page.
     */
    public function isEnabled(): bool
    {
        return (bool)($this->settings['wishlist']['enabled'] ?? false);
    }

    public function add(ServerRequestInterface $request, int $productUid): void
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            $this->wishlistStorage->add($request, $productUid);
            return;
        }
        $this->addPersisted($frontendUser, $productUid);
    }

    public function remove(ServerRequestInterface $request, int $productUid): void
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            $this->wishlistStorage->remove($request, $productUid);
            return;
        }
        $this->removePersisted($frontendUser, $productUid);
    }

    /**
     * Swaps a product's position with its predecessor in the shopper's own arrangement - a no-op
     * if it is already first.
     */
    public function moveUp(ServerRequestInterface $request, int $productUid): void
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            $this->wishlistStorage->moveUp($request, $productUid);
            return;
        }
        $this->swapPersisted($frontendUser, $productUid, -1);
    }

    /**
     * Swaps a product's position with its successor in the shopper's own arrangement - a no-op
     * if it is already last.
     */
    public function moveDown(ServerRequestInterface $request, int $productUid): void
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            $this->wishlistStorage->moveDown($request, $productUid);
            return;
        }
        $this->swapPersisted($frontendUser, $productUid, 1);
    }

    public function contains(ServerRequestInterface $request, int $productUid): bool
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            return in_array($productUid, $this->wishlistStorage->load($request), true);
        }
        return $this->wishlistItemRepository->findOneByFrontendUserAndProduct($frontendUser, $productUid) !== null;
    }

    /**
     * Nav-badge accessor - counts without hydrating Product entities, unlike getItems().
     */
    public function count(ServerRequestInterface $request): int
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            return count($this->wishlistStorage->load($request));
        }
        return $this->wishlistItemRepository->findByFrontendUser($frontendUser)->count();
    }

    /**
     * @return Product[]
     */
    public function getItems(ServerRequestInterface $request): array
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            return $this->sessionProducts($request);
        }
        return $this->persistedProducts($frontendUser);
    }

    /**
     * A guest checkout has no persisted wishlist to purge - a guest's wishlist lives in the FE
     * session instead, keyed to the browser, not to any identity an order could be matched
     * against, so it is deliberately left untouched here.
     */
    public function removeOrderedItems(Order $order): void
    {
        $frontendUser = $order->getFrontendUser();
        if ($frontendUser === 0) {
            return;
        }
        foreach ($order->getItems() as $orderItem) {
            $this->removePersisted($frontendUser, $orderItem->getProduct());
        }
    }

    /**
     * Invoked once, right after a guest with an existing session wishlist logs in - merges those
     * products into the now-identified account's persisted wishlist (addPersisted() already
     * dedupes against anything the account already has) and clears the session copy.
     */
    public function mergeSessionIntoAccount(ServerRequestInterface $request): void
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            return;
        }
        foreach ($this->wishlistStorage->load($request) as $productUid) {
            $this->addPersisted($frontendUser, $productUid);
        }
        $this->wishlistStorage->clear($request);
    }

    private function addPersisted(int $frontendUser, int $productUid): void
    {
        if ($this->wishlistItemRepository->findOneByFrontendUserAndProduct($frontendUser, $productUid) !== null) {
            return;
        }
        $product = $this->productRepository->findByUid($productUid);
        if (!$product instanceof Product) {
            return;
        }
        $item = new WishlistItem();
        $item->setFrontendUser($frontendUser);
        $item->setProduct($product);
        $item->setCreated(new \DateTime());
        $item->setSorting($this->wishlistItemRepository->findByFrontendUser($frontendUser)->count());
        $this->wishlistItemRepository->add($item);
        $this->persistenceManager->persistAll();
    }

    private function removePersisted(int $frontendUser, int $productUid): void
    {
        $item = $this->wishlistItemRepository->findOneByFrontendUserAndProduct($frontendUser, $productUid);
        if ($item === null) {
            return;
        }
        $this->wishlistItemRepository->remove($item);
        $this->persistenceManager->persistAll();
    }

    private function swapPersisted(int $frontendUser, int $productUid, int $direction): void
    {
        $items = array_values(iterator_to_array($this->wishlistItemRepository->findByFrontendUser($frontendUser)));
        $index = null;
        foreach ($items as $key => $item) {
            if ($item->getProduct() instanceof Product && $item->getProduct()->getUid() === $productUid) {
                $index = $key;
                break;
            }
        }
        $swapIndex = $index === null ? null : $index + $direction;
        if ($index === null || $swapIndex === null || !isset($items[$swapIndex])) {
            return;
        }
        $sorting = $items[$index]->getSorting();
        $items[$index]->setSorting($items[$swapIndex]->getSorting());
        $items[$swapIndex]->setSorting($sorting);
        $this->wishlistItemRepository->update($items[$index]);
        $this->wishlistItemRepository->update($items[$swapIndex]);
        $this->persistenceManager->persistAll();
    }

    /**
     * @return Product[]
     */
    private function sessionProducts(ServerRequestInterface $request): array
    {
        $products = [];
        foreach ($this->wishlistStorage->load($request) as $productUid) {
            $product = $this->productRepository->findByUid($productUid);
            if ($product instanceof Product) {
                $products[] = $product;
            }
        }
        return $products;
    }

    /**
     * @return Product[]
     */
    private function persistedProducts(int $frontendUser): array
    {
        $products = [];
        foreach ($this->wishlistItemRepository->findByFrontendUser($frontendUser) as $item) {
            if ($item->getProduct() instanceof Product) {
                $products[] = $item->getProduct();
            }
        }
        return $products;
    }
}
