<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Search;

use GoldeneZeiten\Products\Domain\Dto\Search\SearchResult;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class SearchService
{
    private const DEFAULT_RESULTS_PER_PAGE = 20;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly ProductRepository $productRepository,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    /**
     * A blank term never executes a query - the search form renders with no results section at all
     * rather than a "0 results" state for a search that was never actually attempted.
     */
    public function search(string $term, ?int $categoryUid, int $page): SearchResult
    {
        $term = trim($term);
        if ($term === '') {
            return new SearchResult([], '', 1, 0, 0);
        }

        $page = max(1, $page);
        $perPage = $this->resultsPerPage();
        $totalCount = $this->productRepository->countSearchResults($term, $categoryUid);
        $products = iterator_to_array($this->productRepository->search($term, $categoryUid, ($page - 1) * $perPage, $perPage), false);

        return new SearchResult($products, $term, $page, max(1, (int)ceil($totalCount / $perPage)), $totalCount);
    }

    private function resultsPerPage(): int
    {
        return max(1, (int)($this->settings['search']['resultsPerPage'] ?? self::DEFAULT_RESULTS_PER_PAGE));
    }
}
