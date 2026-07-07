<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Wishlist;

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
 * The two are never merged - a guest's session wishlist is not carried over on login, same
 * simplification legacy's memo control also had (it gated one mode or the other, never both).
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

    public function contains(ServerRequestInterface $request, int $productUid): bool
    {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        if ($frontendUser === 0) {
            return in_array($productUid, $this->wishlistStorage->load($request), true);
        }
        return $this->wishlistItemRepository->findOneByFrontendUserAndProduct($frontendUser, $productUid) !== null;
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
