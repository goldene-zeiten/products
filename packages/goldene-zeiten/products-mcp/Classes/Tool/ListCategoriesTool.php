<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Core\Domain\Dto\Category\CategoryTreeNode;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Walks the category tree one level at a time.
 */
final readonly class ListCategoriesTool implements McpToolInterface
{
    public function __construct(
        private CategoryTreeService $categoryTreeService,
        private ProductRepository $productRepository
    ) {}

    public function getName(): string
    {
        return 'list_categories';
    }

    public function getDescription(): string
    {
        return 'Lists the top-level categories, or the subcategories of a category, with the number of products '
            . 'that are directly in each. The uid filters search_products.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'parent' => ['type' => 'integer', 'description' => 'Uid of the category whose subcategories to list.'],
            ],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $nodes = isset($arguments['parent']) ? $this->childrenOf((int)$arguments['parent']) : $this->categoryTreeService->getTree();

        return [
            'categories' => array_map($this->describe(...), $nodes),
        ];
    }

    /**
     * @return CategoryTreeNode[]
     */
    private function childrenOf(int $parentUid): array
    {
        $subtree = $this->categoryTreeService->getSubtree($parentUid);
        if ($subtree === []) {
            throw new ToolFailedException(sprintf('There is no category with the uid %d.', $parentUid), 1792710233);
        }

        return $subtree[0]->getChildren();
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(CategoryTreeNode $node): array
    {
        $category = $node->getCategory();

        return [
            'uid' => $category->getUid(),
            'title' => $category->getTitle(),
            'hasChildren' => $node->getChildren() !== [],
            'productCount' => $this->productRepository->countByCategory($category),
        ];
    }
}
