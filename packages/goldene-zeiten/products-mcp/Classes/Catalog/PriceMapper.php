<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Pricing\PriceProviderInterface;
use GoldeneZeiten\Products\Core\Pricing\VariantPriceRangeResolver;
use GoldeneZeiten\Products\Core\Service\TaxService;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Prices as the shop shows them: the unit price of the same price provider the product templates use, in the
 * pricing mode of the site (with or without VAT), together with the currency.
 */
final readonly class PriceMapper
{
    public function __construct(
        private PriceProviderInterface $priceProvider,
        private VariantPriceRangeResolver $priceRangeResolver,
        private ProductsConfigurationFactory $configurationFactory,
        private TaxService $taxService
    ) {}

    /**
     * The price of a product with variants is its cheapest variant, flagged as a "from" price.
     *
     * @return array{amount: string, includesVat: bool, currency: string, from: bool}
     */
    public function forProduct(Product $product, McpContext $context): array
    {
        $configuration = $this->configurationFactory->createFromSite($context->site);
        $range = $this->priceRangeResolver->resolve($product, $context->request);
        if ($range === null) {
            $price = $this->priceProvider->getUnitPrice($product, null, 1, $context->request);

            return $this->priceData($price, false, $configuration);
        }

        return $this->priceData($range->getLowest(), $range->getLowest()->getCents() !== $range->getHighest()->getCents(), $configuration);
    }

    /**
     * @return array{amount: string, includesVat: bool, currency: string, from: bool}
     */
    public function forArticle(Product $product, Article $article, McpContext $context): array
    {
        $configuration = $this->configurationFactory->createFromSite($context->site);
        $price = $this->priceProvider->getUnitPrice($product, $article, 1, $context->request);

        return $this->priceData($price, false, $configuration);
    }

    /**
     * The unit price a customer pays including VAT, for totals that are always gross.
     */
    public function grossUnitPrice(Product $product, ?Article $article, int $quantity, McpContext $context): Money
    {
        $configuration = $this->configurationFactory->createFromSite($context->site);
        $price = $this->priceProvider->getUnitPrice($product, $article, $quantity, $context->request);
        if ($configuration->getPricingMode() === 'gross') {
            return $price;
        }

        return $price->multiply(1 + $this->taxService->getTaxRate($configuration, $product->getTaxClass()));
    }

    /**
     * @return array{amount: string, includesVat: bool, currency: string, from: bool}
     */
    private function priceData(Money $price, bool $from, ProductsConfiguration $configuration): array
    {
        return [
            'amount' => $price->getDecimalString(),
            'includesVat' => $configuration->getPricingMode() === 'gross',
            'currency' => $configuration->getCurrency(),
            'from' => $from,
        ];
    }
}
