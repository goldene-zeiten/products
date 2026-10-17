<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Seo;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductSeoMetadata;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Event\ModifyProductStructuredDataEvent;
use GoldeneZeiten\Products\Core\PageTitle\CurrentProductHolder;
use GoldeneZeiten\Products\Core\Pricing\PriceProviderInterface;
use GoldeneZeiten\Products\Core\Pricing\VariantPriceRangeResolver;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * Writes the head of a product detail page: the SEO title for the page title provider, the meta and Open
 * Graph tags, and the schema.org Product data as JSON-LD.
 */
final class ProductHeadRenderer
{
    public function __construct(
        private readonly ProductSeoResolver $seoResolver,
        private readonly CurrentProductHolder $currentProductHolder,
        private readonly MetaTagManagerRegistry $metaTagManagerRegistry,
        private readonly PageRenderer $pageRenderer,
        private readonly PriceProviderInterface $priceProvider,
        private readonly VariantPriceRangeResolver $priceRangeResolver,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function render(Product $product, ?Article $article, ServerRequestInterface $request): void
    {
        $metadata = $this->seoResolver->resolve($product, $article);
        $this->currentProductHolder->setSeoTitle($metadata->title);
        $imageUrl = $this->imageUrl($metadata, $request);
        $tags = [
            'description' => $metadata->description,
            'keywords' => $metadata->keywords,
            'robots' => $metadata->noIndex ? 'noindex,follow' : '',
            'og:title' => $metadata->ogTitle,
            'og:description' => $metadata->ogDescription,
            'og:image' => $imageUrl,
            'og:type' => 'product',
        ];
        foreach (array_filter($tags, static fn(string $content): bool => $content !== '') as $property => $content) {
            $type = str_starts_with($property, 'og:') ? 'property' : 'name';
            $this->metaTagManagerRegistry->getManagerForProperty($property)->addProperty($property, $content, [], true, $type);
        }
        $this->renderStructuredData($product, $article, $metadata, $imageUrl, $request);
    }

    private function renderStructuredData(Product $product, ?Article $article, ProductSeoMetadata $metadata, string $imageUrl, ServerRequestInterface $request): void
    {
        $event = new ModifyProductStructuredDataEvent($this->structuredData($product, $article, $metadata, $imageUrl, $request), $product, $article, $request);
        $this->eventDispatcher->dispatch($event);
        if ($event->getData() === []) {
            return;
        }
        $this->pageRenderer->addHeaderData(sprintf(
            '<script type="application/ld+json">%s</script>',
            json_encode($event->getData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(Product $product, ?Article $article, ProductSeoMetadata $metadata, string $imageUrl, ServerRequestInterface $request): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getTitle(),
            'sku' => $article?->getItemNumber() ?: $product->getItemNumber(),
            'offers' => $this->offers($product, $article, $request),
        ];
        if ($metadata->description !== '') {
            $data['description'] = $metadata->description;
        }
        if ($imageUrl !== '') {
            $data['image'] = $imageUrl;
        }
        return $data;
    }

    /**
     * Before a variant is chosen, a product priced through its variants offers their price range.
     *
     * @return array<string, mixed>
     */
    private function offers(Product $product, ?Article $article, ServerRequestInterface $request): array
    {
        $offer = [
            'priceCurrency' => $this->configurationFactory->create($request)->getCurrency(),
            'url' => (string)$request->getUri(),
        ];
        $priceRange = $article === null ? $this->priceRangeResolver->resolve($product, $request) : null;
        if ($priceRange !== null) {
            return [
                '@type' => 'AggregateOffer',
                'lowPrice' => $priceRange->getLowest()->getDecimalString(),
                'highPrice' => $priceRange->getHighest()->getDecimalString(),
                'offerCount' => $priceRange->getVariantCount(),
                'availability' => $this->availability($priceRange->isAnyInStock()),
            ] + $offer;
        }
        $inStock = ($article ?? $product)->isUnlimitedStock() || ($article ?? $product)->getInStock() > 0;
        return [
            '@type' => 'Offer',
            'price' => $this->priceProvider->getUnitPrice($product, $article, 1, $request)->getDecimalString(),
            'availability' => $this->availability($inStock),
        ] + $offer;
    }

    private function availability(bool $inStock): string
    {
        return $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    }

    private function imageUrl(ProductSeoMetadata $metadata, ServerRequestInterface $request): string
    {
        $publicUrl = $metadata->ogImage?->getOriginalResource()->getPublicUrl();
        if ($publicUrl === null) {
            return '';
        }
        if (preg_match('#^https?://#', $publicUrl) === 1) {
            return $publicUrl;
        }
        $normalizedParams = $request->getAttribute('normalizedParams');
        $siteUrl = $normalizedParams instanceof NormalizedParams ? $normalizedParams->getSiteUrl() : '/';
        return rtrim($siteUrl, '/') . '/' . ltrim($publicUrl, '/');
    }
}
