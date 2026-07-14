<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Search;

use GoldeneZeiten\Products\Core\Domain\Dto\Search\SearchResult;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class SearchService
{
    private const DEFAULT_RESULTS_PER_PAGE = 20;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {}

    public function search(string $term, ?int $categoryUid, int $page, ServerRequestInterface $request): SearchResult
    {
        $term = trim($term);
        if ($term === '') {
            return new SearchResult([], '', 1, 0, 0);
        }

        $page = max(1, $page);
        $perPage = $this->resultsPerPage($request);
        $totalCount = $this->productRepository->countSearchResults($term, $categoryUid);
        $products = iterator_to_array($this->productRepository->search($term, $categoryUid, ($page - 1) * $perPage, $perPage), false);

        return new SearchResult($products, $term, $page, max(1, (int)ceil($totalCount / $perPage)), $totalCount);
    }

    private function resultsPerPage(ServerRequestInterface $request): int
    {
        $this->configurationManager->setRequest($request);
        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'ProductsCore'
        );
        return max(1, (int)($settings['search']['resultsPerPage'] ?? self::DEFAULT_RESULTS_PER_PAGE));
    }
}
