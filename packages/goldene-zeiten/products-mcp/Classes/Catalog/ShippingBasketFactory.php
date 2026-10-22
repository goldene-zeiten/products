<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Turns the items an assistant names into the shipping context carriers decide on, without a basket in a
 * session: the weights, shipping classes and gross goods total are those the basket would report.
 */
final readonly class ShippingBasketFactory
{
    public function __construct(
        private ProductFinder $productFinder,
        private PriceMapper $priceMapper,
        private ProductsConfigurationFactory $configurationFactory
    ) {}

    /**
     * @param list<array{product: int, article?: int, quantity?: int}> $items
     * @throws ToolFailedException when an item is not a visible product, or an article of it
     */
    public function create(array $items, string $country, McpContext $context): ShippingContext
    {
        $contextItems = [];
        $goodsTotal = Money::fromCents(0);
        foreach ($items as $item) {
            $product = $this->productFinder->require((int)$item['product'], $context);
            $article = $this->article($product, isset($item['article']) ? (int)$item['article'] : null);
            $quantity = max(1, (int)($item['quantity'] ?? 1));
            $contextItems[] = $this->contextItem($product, $article, $quantity);
            $unitPrice = $this->priceMapper->grossUnitPrice($product, $article, $quantity, $context);
            $goodsTotal = $goodsTotal->add($unitPrice->multiply($quantity));
        }
        $weight = array_sum(array_map(
            static fn(ShippingContextItem $item): int => $item->getWeight() * $item->getQuantity(),
            $contextItems
        ));
        $currency = $this->configurationFactory->createFromSite($context->site)->getCurrency();

        return new ShippingContext($contextItems, $weight, $goodsTotal, $currency, $country, '', 0, $context->site);
    }

    private function contextItem(Product $product, ?Article $article, int $quantity): ShippingContextItem
    {
        return new ShippingContextItem(
            $quantity,
            $this->unitWeight($product, $article),
            $product->isBulky() || ($article?->isBulky() ?? false),
            $product->getShippingClass()
        );
    }

    private function article(Product $product, ?int $articleUid): ?Article
    {
        if ($articleUid === null) {
            return null;
        }
        foreach ($product->getArticles() as $article) {
            if ($article->getUid() === $articleUid) {
                return $article;
            }
        }

        throw new ToolFailedException(
            sprintf('The article %d is not a variant of the product %d.', $articleUid, (int)$product->getUid()),
            1792710231
        );
    }

    private function unitWeight(Product $product, ?Article $article): int
    {
        $articleWeight = $article?->getWeight() ?? 0;

        return $articleWeight > 0 ? $articleWeight : $product->getWeight();
    }
}
