<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Search\Service;

use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Search\Domain\Dto\SearchResult;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;

final class SearchService
{
    private const DEFAULT_RESULTS_PER_PAGE = 20;

    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    public function search(string $term, ?int $categoryUid, int $page, ServerRequestInterface $request): SearchResult
    {
        $term = trim($term);
        if ($term === '') {
            return new SearchResult('', null, null);
        }

        $perPage = $this->resultsPerPage($request);
        $products = $this->productRepository->findBySearchTerm($term, $categoryUid);
        $paginator = new QueryResultPaginator($products, max(1, $page), $perPage);

        return new SearchResult($term, $paginator, new SlidingWindowPagination($paginator, 5), $products->count());
    }

    private function resultsPerPage(ServerRequestInterface $request): int
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof SiteInterface) {
            return self::DEFAULT_RESULTS_PER_PAGE;
        }
        return max(1, (int)$site->getSettings()->get('products.search.resultsPerPage', self::DEFAULT_RESULTS_PER_PAGE));
    }
}
