<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Manufacturer;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * The full description of one product: texts without markup, price, variants, brand and categories.
 */
final readonly class ProductDetailMapper
{
    public function __construct(
        private PriceMapper $priceMapper,
        private ProductUrlFactory $urlFactory,
        private ProductSummaryMapper $summaryMapper
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function map(Product $product, McpContext $context): array
    {
        $summary = $this->summaryMapper->map($product, $context);

        return [
            'uid' => $product->getUid(),
            'title' => $product->getTitle(),
            'subtitle' => $product->getSubtitle(),
            'description' => $this->plainText($product->getDescription()),
            'itemNumber' => $product->getItemNumber(),
            'price' => $summary['price'],
            'inStock' => $summary['inStock'],
            'variants' => array_map(
                fn(Article $article): array => $this->variant($product, $article, $context),
                $product->getArticles()->toArray()
            ),
            'manufacturer' => $this->manufacturer($product->getManufacturer()),
            'categories' => array_map(
                static fn(Category $category): string => $category->getTitle(),
                $product->getCategories()->toArray()
            ),
            'url' => $this->urlFactory->create($product, $context),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function variant(Product $product, Article $article, McpContext $context): array
    {
        $attributes = [];
        foreach ($article->getAttributeValues() as $value) {
            $attributes[$value->getAttribute()?->getTitle() ?? ''] = $value->getValue();
        }

        return [
            'uid' => $article->getUid(),
            'title' => $article->getTitle(),
            'itemNumber' => $article->getItemNumber(),
            'attributes' => $attributes,
            'price' => $this->priceMapper->forArticle($product, $article, $context),
            'stock' => $article->getInStock(),
            'unlimitedStock' => $article->isUnlimitedStock(),
        ];
    }

    /**
     * @return array{brand: string, name: string, website: string}|null
     */
    private function manufacturer(?Manufacturer $manufacturer): ?array
    {
        if ($manufacturer === null) {
            return null;
        }

        return [
            'brand' => $manufacturer->getBrandName(),
            'name' => $manufacturer->getName(),
            'website' => $manufacturer->getWebsite(),
        ];
    }

    private function plainText(string $html): string
    {
        $text = (string)preg_replace('#</(p|div|li|h[1-6])>|<br\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string)preg_replace("/[ \t]*\n[ \t\n]*/", "\n", $text));
    }
}
