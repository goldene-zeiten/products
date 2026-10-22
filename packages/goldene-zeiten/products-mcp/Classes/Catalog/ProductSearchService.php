<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListCriteria;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityResolver;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Searches the catalogue with the list query of the product list, so the products found are the ones the
 * frontend lists: the term matches like the shop search, and the category covers its subcategories.
 */
final readonly class ProductSearchService
{
    public function __construct(
        private ProductRepository $productRepository,
        private ProductVisibilityResolver $visibilityResolver,
        private CategoryResolver $categoryResolver,
        private PriceRangeFilter $priceRangeFilter,
        private StorageScope $storageScope
    ) {}

    /**
     * @return array{total: int, products: list<Product>}
     */
    public function search(ProductSearchRequest $request, McpContext $context): array
    {
        $criteria = $this->criteria($request);
        if ($criteria === null) {
            return ['total' => 0, 'products' => []];
        }
        $query = $this->productRepository->createListQuery($criteria);
        $this->storageScope->apply($query, $context->site);
        $this->addConstraints($query, $this->filterConstraints($query, $request));
        $total = $query->execute()->count();
        $query->setOffset(($request->page - 1) * $request->perPage);
        $query->setLimit($request->perPage);

        return ['total' => $total, 'products' => $this->visible($query, $context)];
    }

    /**
     * @return ProductListCriteria|null null when nothing can match the term
     */
    private function criteria(ProductSearchRequest $request): ?ProductListCriteria
    {
        $productUids = [];
        if ($request->term !== '') {
            $productUids = $this->productRepository->findUidsMatchingTerm($request->term);
            if ($productUids === []) {
                return null;
            }
        }
        $categoryUids = $request->categoryUid === null ? [] : $this->categoryResolver->subtreeUids($request->categoryUid);

        return new ProductListCriteria(productUids: $productUids, categoryUids: $categoryUids);
    }

    /**
     * @param QueryInterface<Product> $query
     * @return list<ConstraintInterface>
     */
    private function filterConstraints(QueryInterface $query, ProductSearchRequest $request): array
    {
        $constraints = [];
        if ($request->manufacturerUid !== null) {
            $constraints[] = $query->equals('manufacturer', $request->manufacturerUid);
        }
        if ($request->minPrice !== null || $request->maxPrice !== null) {
            $uids = $this->priceRangeFilter->findProductUids($request->minPrice, $request->maxPrice);
            $constraints[] = $uids === [] ? $query->equals('uid', 0) : $query->in('uid', $uids);
        }

        return $constraints;
    }

    /**
     * @param QueryInterface<Product> $query
     * @param list<ConstraintInterface> $constraints
     */
    private function addConstraints(QueryInterface $query, array $constraints): void
    {
        if ($constraints === []) {
            return;
        }
        $existing = $query->getConstraint();
        $query->matching($query->logicalAnd(...($existing !== null ? [$existing, ...$constraints] : $constraints)));
    }

    /**
     * @param QueryInterface<Product> $query
     * @return list<Product>
     */
    private function visible(QueryInterface $query, McpContext $context): array
    {
        return array_values($this->visibilityResolver->filterVisible($query->execute()->toArray(), $context->request));
    }
}
