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
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Picks persisted or FE-session backend by login state; merges session wishlist on login.
 */
final class WishlistService
{
    public function __construct(
        private readonly WishlistItemRepository $wishlistItemRepository,
        private readonly WishlistStorage $wishlistStorage,
        private readonly ProductRepository $productRepository,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly PersistenceManagerInterface $persistenceManager
    ) {}

    public function isEnabled(ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof SiteInterface) {
            return false;
        }
        return (bool)$site->getSettings()->get('products.wishlist.enabled', false);
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

    public function moveUp(ServerRequestInterface $request, int $productUid): void
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            $this->wishlistStorage->moveUp($request, $productUid);
            return;
        }
        $this->swapPersisted($frontendUser, $productUid, -1);
    }

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
