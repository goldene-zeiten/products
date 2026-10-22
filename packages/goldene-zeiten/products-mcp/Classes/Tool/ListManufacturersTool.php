<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Core\Domain\Model\Manufacturer;
use GoldeneZeiten\Products\Core\Domain\Repository\ManufacturerRepository;
use GoldeneZeiten\Products\Mcp\Catalog\CategoryResolver;
use GoldeneZeiten\Products\Mcp\Catalog\StorageScope;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Lists the manufacturers that have products, optionally only those with products in a category.
 */
final readonly class ListManufacturersTool implements McpToolInterface
{
    public function __construct(
        private ManufacturerRepository $manufacturerRepository,
        private CategoryResolver $categoryResolver,
        private StorageScope $storageScope
    ) {}

    public function getName(): string
    {
        return 'list_manufacturers';
    }

    public function getDescription(): string
    {
        return 'Lists the manufacturers with products, optionally only those with products in a category or its '
            . 'subcategories. The uid filters search_products.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'integer', 'description' => 'Uid of a category from list_categories.'],
            ],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $groups = isset($arguments['category']) ? [$this->categoryResolver->subtreeUids((int)$arguments['category'])] : [];

        return [
            'manufacturers' => array_map(
                static fn(Manufacturer $manufacturer): array => [
                    'uid' => $manufacturer->getUid(),
                    'brand' => $manufacturer->getBrandName(),
                    'name' => $manufacturer->getName(),
                    'website' => $manufacturer->getWebsite(),
                ],
                $this->manufacturerRepository->findWithProductsInCategories($groups, $this->storageScope->storagePageIds($context->site))
            ),
        ];
    }
}
