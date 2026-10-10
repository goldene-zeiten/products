<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Basket;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Domain\ValueObject\VatBuckets;
use GoldeneZeiten\Products\Core\Event\BasketUpdatedEvent;
use GoldeneZeiten\Products\Core\Event\ModifyBasketItemEvent;
use GoldeneZeiten\Products\Core\Pricing\GraduatedPriceProvider;
use GoldeneZeiten\Products\Core\Pricing\PriceProviderInterface;
use GoldeneZeiten\Products\Core\Pricing\VatSplitter;
use GoldeneZeiten\Products\Core\Service\PriceRoundingService;
use GoldeneZeiten\Products\Core\Service\TaxService;
use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityResolver;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductVisibilityResolver $productVisibilityResolver,
        private readonly VatSplitter $vatSplitter
    ) {}

    public function add(ServerRequestInterface $request, int $productUid, ?int $articleUid, int $quantity): void
    {
        $product = $this->findVisibleProduct($productUid, $request);
        if ($product === null) {
            return;
        }

        $article = $this->resolveArticle($articleUid);
        if (!$this->isArticleOfProduct($product, $articleUid, $article)) {
            return;
        }

        $basket = $this->basketStorage->load($request);
        $lineQuantity = $basket->getQuantity($productUid, $articleUid) + max(1, $quantity);
        $lineQuantity = $this->basketQuantityResolver->clamp($product, $article, $lineQuantity);
        $basket->replaceItem(new BasketItem($productUid, $articleUid, $lineQuantity));
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

    /**
     * A line is priced from its article but titled from its product, so the article must be one of the
     * product's own; a product with variants cannot be bought without one.
     */
    private function isArticleOfProduct(Product $product, ?int $articleUid, ?Article $article): bool
    {
        if ($articleUid === null) {
            return $product->getArticles()->count() === 0;
        }

        return $article instanceof Article && $article->getProduct()?->getUid() === $product->getUid();
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

    /**
     * The tax country decides the VAT rate of every line; without one the shop's default country applies.
     * Pass the country the order will be taxed in, so items and shipping use the same country's rates.
     */
    public function getBasketViewModel(ServerRequestInterface $request, ?string $taxCountry = null): BasketViewModel
    {
        $basket = $this->basketStorage->load($request);
        $configuration = $this->configurationFactory->create($request);
        $taxCountry = $taxCountry !== '' ? $taxCountry : null;

        $viewItems = [];
        foreach ($basket->getItems() as $item) {
            $viewItem = $this->resolveItem($item, $configuration, $request, $taxCountry);
            if ($viewItem !== null) {
                $viewItems[] = $viewItem;
            }
        }

        return $this->buildViewModel($viewItems, $configuration);
    }

    /**
     * The rounding mode moves the gross total; the difference is split across the lines' VAT rates, so
     * net and VAT move with it and still add up to the gross.
     *
     * @param BasketViewItem[] $viewItems
     */
    private function buildViewModel(array $viewItems, ProductsConfiguration $configuration): BasketViewModel
    {
        $totalNet = Money::fromCents(0);
        $totalGross = Money::fromCents(0);
        $buckets = VatBuckets::empty();
        foreach ($viewItems as $viewItem) {
            $totalNet = $totalNet->add($viewItem->getLineTotalNet());
            $totalGross = $totalGross->add($viewItem->getLineTotalGross());
            $buckets = $buckets->with($viewItem->getTaxRate(), $viewItem->getLineTotalGross());
        }
        $roundedGross = $this->priceRoundingService->round($totalGross, $configuration->getRoundingMode());
        foreach ($this->vatSplitter->split($roundedGross->subtract($totalGross), $buckets) as $part) {
            $totalNet = $totalNet->add($part['amount']->netFromGross($part['rate']));
        }

        return new BasketViewModel($viewItems, $totalNet, $roundedGross, $roundedGross->subtract($totalNet), $configuration->getCurrency());
    }

    private function findVisibleProduct(int $productUid, ServerRequestInterface $request): ?Product
    {
        $product = $this->productRepository->findByUid($productUid);
        if (!$product instanceof Product || !$this->productVisibilityResolver->isVisible($product, $request)) {
            return null;
        }

        return $product;
    }

    private function resolveItem(BasketItem $item, ProductsConfiguration $configuration, ServerRequestInterface $request, ?string $taxCountry): ?BasketViewItem
    {
        $product = $this->findVisibleProduct($item->getProductUid(), $request);
        if ($product === null) {
            return null;
        }

        $article = $this->resolveArticle($item->getArticleUid());
        if (!$this->isArticleOfProduct($product, $item->getArticleUid(), $article)) {
            return null;
        }

        $basePrice = $this->priceProvider->getUnitPrice($product, $article, $item->getQuantity(), $request);
        $taxRate = $this->taxService->getTaxRate($configuration, $product->getTaxClass(), $taxCountry);
        $unitPriceGross = $configuration->getPricingMode() === 'gross' ? $basePrice : $basePrice->multiply(1 + $taxRate);

        // Net and VAT of the line derive from its gross, so rounding a unit's net never multiplies by the quantity.
        $lineTotalGross = $unitPriceGross->multiply($item->getQuantity());
        $lineTotalNet = $lineTotalGross->netFromGross($taxRate);

        $viewItem = new BasketViewItem(
            $product,
            $article,
            $item->getQuantity(),
            $unitPriceGross->netFromGross($taxRate),
            $unitPriceGross,
            $taxRate,
            $lineTotalNet,
            $lineTotalGross,
            $lineTotalGross->subtract($lineTotalNet)
        );

        $event = new ModifyBasketItemEvent($viewItem, $request);
        $this->eventDispatcher->dispatch($event);
        return $event->getViewItem();
    }
}
