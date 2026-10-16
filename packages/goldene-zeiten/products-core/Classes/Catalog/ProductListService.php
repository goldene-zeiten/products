<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Catalog;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListCriteria;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListSettings;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Event\ModifyProductListEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Selects the products of a list and pages them. Built-in listings are one database query - selection,
 * sorting and paging happen in SQL, so a list stays fast with thousands of products. A listing an add-on
 * registered selects its products itself and is paged in memory.
 */
final class ProductListService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductListModeRegistry $productListModeRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function paginate(
        ServerRequestInterface $request,
        ProductListCriteria $criteria,
        ProductListSettings $settings,
        int $currentPage,
    ): PaginatorInterface {
        $currentPage = $settings->pagination ? max(1, $currentPage) : 1;
        if ($this->productListModeRegistry->has($criteria->mode)) {
            $products = $this->filterByCriteria(
                $this->productListModeRegistry->findProducts($criteria->mode, new ProductListContext($request)),
                $criteria
            );
            return new ArrayPaginator($products, $currentPage, $this->itemsPerPage($settings, count($products)));
        }
        $query = $this->productRepository->createListQuery($criteria);
        $this->applyListeners($query, $criteria, $request);
        $result = $query->execute();
        return new QueryResultPaginator($result, $currentPage, $this->itemsPerPage($settings, $result->count()));
    }

    /**
     * The page links around the current page, as many as `products.list.maximumNumberOfLinks` allows.
     */
    public function pagination(PaginatorInterface $paginator, ?Site $site): SlidingWindowPagination
    {
        $maximumNumberOfLinks = (int)($site?->getSettings()->get('products.list.maximumNumberOfLinks', 5) ?? 5);
        return new SlidingWindowPagination($paginator, max(1, $maximumNumberOfLinks));
    }

    /**
     * @param QueryInterface<Product> $query
     */
    private function applyListeners(QueryInterface $query, ProductListCriteria $criteria, ServerRequestInterface $request): void
    {
        $event = new ModifyProductListEvent($query, $criteria, $request);
        $this->eventDispatcher->dispatch($event);
        $constraints = $event->getConstraints();
        if ($constraints === []) {
            return;
        }
        $existing = $query->getConstraint();
        $query->matching($query->logicalAnd(...($existing !== null ? [$existing, ...$constraints] : $constraints)));
    }

    /**
     * @param Product[] $products
     * @return Product[]
     */
    private function filterByCriteria(array $products, ProductListCriteria $criteria): array
    {
        $filtered = [];
        foreach ($products as $product) {
            if ($criteria->productUids !== [] && !in_array($product->getUid() ?? 0, $criteria->productUids, true)) {
                continue;
            }
            if ($criteria->categoryUids !== [] && !$this->isInCategories($product, $criteria->categoryUids)) {
                continue;
            }
            $filtered[] = $product;
        }
        return $filtered;
    }

    /**
     * @param int[] $categoryUids
     */
    private function isInCategories(Product $product, array $categoryUids): bool
    {
        foreach ($product->getCategories() as $category) {
            if (in_array($category->getUid() ?? 0, $categoryUids, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Without a page size the whole list is one page.
     */
    private function itemsPerPage(ProductListSettings $settings, int $total): int
    {
        return $settings->isPaged() ? $settings->itemsPerPage : max(1, $total);
    }
}
