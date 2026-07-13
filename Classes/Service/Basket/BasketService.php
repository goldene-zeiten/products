<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Basket;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Domain\Dto\Basket;
use GoldeneZeiten\Products\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\BasketUpdatedEvent;
use GoldeneZeiten\Products\Event\ModifyBasketItemEvent;
use GoldeneZeiten\Products\Pricing\GraduatedPriceProvider;
use GoldeneZeiten\Products\Pricing\PriceProviderInterface;
use GoldeneZeiten\Products\Service\PriceRoundingService;
use GoldeneZeiten\Products\Service\TaxService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BasketService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly TaxService $taxService,
        private readonly BasketStorage $basketStorage,
        private readonly PriceProviderInterface $priceProvider,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly PriceRoundingService $priceRoundingService,
        private readonly BasketQuantityResolver $basketQuantityResolver,
        private readonly GraduatedPriceProvider $graduatedPriceProvider,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function add(ServerRequestInterface $request, int $productUid, ?int $articleUid, int $quantity): void
    {
        $product = $this->productRepository->findByUid($productUid);
        if (!$product instanceof Product) {
            return;
        }

        if ($articleUid === null && $product->getArticles()->count() > 0) {
            return;
        }

        $article = $this->resolveArticle($articleUid);
        $quantity = $this->basketQuantityResolver->clamp($product, $article, $quantity);

        $basket = $this->basketStorage->load($request);
        $basket->addItem(new BasketItem($productUid, $articleUid, $quantity));
        $basket = $this->dispatchBasketUpdated($request, $basket);
        $this->basketStorage->save($request, $basket);
    }

    public function update(ServerRequestInterface $request, int $productUid, ?int $articleUid, int $quantity): void
    {
        if ($quantity > 0) {
            $product = $this->productRepository->findByUid($productUid);
            if ($product instanceof Product) {
                $quantity = $this->basketQuantityResolver->clamp($product, $this->resolveArticle($articleUid), $quantity);
            }
        }

        $basket = $this->basketStorage->load($request);
        $basket->updateQuantity($productUid, $articleUid, $quantity);
        $basket = $this->dispatchBasketUpdated($request, $basket);
        $this->basketStorage->save($request, $basket);
    }

    private function dispatchBasketUpdated(ServerRequestInterface $request, Basket $basket): Basket
    {
        $event = new BasketUpdatedEvent($basket, $request);
        $this->eventDispatcher->dispatch($event);
        return $event->getBasket();
    }

    private function resolveArticle(?int $articleUid): ?Article
    {
        if ($articleUid === null) {
            return null;
        }
        $article = $this->articleRepository->findByUid($articleUid);
        return $article instanceof Article ? $article : null;
    }

    public function remove(ServerRequestInterface $request, int $productUid, ?int $articleUid): void
    {
        $basket = $this->basketStorage->load($request);
        $basket->removeItem($productUid, $articleUid);
        $basket = $this->dispatchBasketUpdated($request, $basket);
        $this->basketStorage->save($request, $basket);
    }

    public function clear(ServerRequestInterface $request): void
    {
        $basket = $this->dispatchBasketUpdated($request, new Basket());
        $this->basketStorage->save($request, $basket);
    }

    /**
     * Check if any line is already discounted (category/usergroup, not quantity-tiers).
     */
    public function isAlreadyDiscounted(ServerRequestInterface $request): bool
    {
        foreach ($this->basketStorage->load($request)->getItems() as $item) {
            $product = $this->productRepository->findByUid($item->getProductUid());
            if (!$product instanceof Product) {
                continue;
            }
            $article = $this->resolveArticle($item->getArticleUid());
            $undiscounted = $this->graduatedPriceProvider->getUnitPrice($product, $article, $item->getQuantity(), $request);
            $discounted = $this->priceProvider->getUnitPrice($product, $article, $item->getQuantity(), $request);
            if ($undiscounted->getCents() !== $discounted->getCents()) {
                return true;
            }
        }
        return false;
    }

    public function addVoucherCode(ServerRequestInterface $request, string $voucherCode): void
    {
        $basket = $this->basketStorage->load($request);
        $basket->addVoucherCode($voucherCode);
        $this->basketStorage->save($request, $basket);
    }

    public function removeVoucherCode(ServerRequestInterface $request, string $voucherCode): void
    {
        $basket = $this->basketStorage->load($request);
        $basket->removeVoucherCode($voucherCode);
        $this->basketStorage->save($request, $basket);
    }

    public function clearVoucherCodes(ServerRequestInterface $request): void
    {
        $basket = $this->basketStorage->load($request);
        $basket->clearVoucherCodes();
        $this->basketStorage->save($request, $basket);
    }

    /**
     * @return string[]
     */
    public function getAppliedVoucherCodes(ServerRequestInterface $request): array
    {
        return $this->basketStorage->load($request)->getVoucherCodes();
    }

    public function getBasketViewModel(ServerRequestInterface $request): BasketViewModel
    {
        $basket = $this->basketStorage->load($request);
        $viewItems = [];
        $totalNetCents = 0;
        $totalGrossCents = 0;
        $totalTaxCents = 0;

        $configuration = $this->configurationFactory->create($request);

        foreach ($basket->getItems() as $item) {
            $viewItem = $this->resolveItem($item, $configuration, $request);
            if ($viewItem === null) {
                continue;
            }
            $viewItems[] = $viewItem;
            $totalNetCents += $viewItem->getLineTotalNet()->getCents();
            $totalGrossCents += $viewItem->getLineTotalGross()->getCents();
            $totalTaxCents += $viewItem->getLineTotalTax()->getCents();
        }

        return new BasketViewModel(
            $viewItems,
            Money::fromCents($totalNetCents),
            $this->priceRoundingService->round(Money::fromCents($totalGrossCents), $configuration->getRoundingMode()),
            Money::fromCents($totalTaxCents),
            $configuration->getCurrency()
        );
    }

    private function resolveItem(BasketItem $item, ProductsConfiguration $configuration, ServerRequestInterface $request): ?BasketViewItem
    {
        $product = $this->productRepository->findByUid($item->getProductUid());
        if (!$product instanceof Product) {
            return null;
        }

        $article = null;
        if ($item->getArticleUid() !== null) {
            $article = $this->articleRepository->findByUid($item->getArticleUid());
        }

        $basePrice = $this->priceProvider->getUnitPrice($product, $article, $item->getQuantity(), $request);
        $taxRate = $this->taxService->getTaxRate($configuration, $product->getTaxClass());

        if ($configuration->getPricingMode() === 'gross') {
            $unitPriceGross = $basePrice;
            $unitPriceNet = $unitPriceGross->netFromGross($taxRate);
        } else {
            $unitPriceNet = $basePrice;
            $unitPriceGross = Money::fromCents((int)round($unitPriceNet->getCents() * (1 + $taxRate)));
        }

        $lineTotalNet = $unitPriceNet->multiply($item->getQuantity());
        $lineTotalGross = $unitPriceGross->multiply($item->getQuantity());
        $lineTotalTax = $lineTotalGross->subtract($lineTotalNet);

        $viewItem = new BasketViewItem(
            $product,
            $article,
            $item->getQuantity(),
            $unitPriceNet,
            $unitPriceGross,
            $taxRate,
            $lineTotalNet,
            $lineTotalGross,
            $lineTotalTax
        );

        $event = new ModifyBasketItemEvent($viewItem, $request);
        $this->eventDispatcher->dispatch($event);
        return $event->getViewItem();
    }
}
