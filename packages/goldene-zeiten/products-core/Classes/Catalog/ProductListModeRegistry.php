<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Catalog;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListContext;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * The product-list modes integrators have registered. It lets the product controller ask whether a
 * stored mode is one of them and get its products, and lets the content element offer them as choices.
 */
final class ProductListModeRegistry
{
    /**
     * @var array<string, ProductListModeProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<ProductListModeProviderInterface> $providers
     */
    public function __construct(
        #[TaggedIterator('products.product_list_mode')]
        iterable $providers
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->getMode()] = $provider;
        }
    }

    public function has(string $mode): bool
    {
        return isset($this->providers[$mode]);
    }

    /**
     * @return Product[]
     */
    public function findProducts(string $mode, ProductListContext $context): array
    {
        return isset($this->providers[$mode]) ? $this->providers[$mode]->findProducts($context) : [];
    }

    /**
     * The registered modes as backend select items - value plus label - for the content element to offer.
     *
     * @return array<array{label: string, value: string}>
     */
    public function getSelectItems(): array
    {
        $items = [];
        foreach ($this->providers as $provider) {
            $items[] = ['label' => $provider->getLabel(), 'value' => $provider->getMode()];
        }

        return $items;
    }
}
