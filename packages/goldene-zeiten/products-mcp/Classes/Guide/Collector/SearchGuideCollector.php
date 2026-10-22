<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Guide\Collector;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * The variables of the search guide: what the shop search can match and how many hits a page holds.
 */
final readonly class SearchGuideCollector
{
    private const DEFAULT_RESULTS_PER_PAGE = 20;

    public function __construct(
        private PackageManager $packageManager
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(McpContext $context): array
    {
        $hasSearch = $this->packageManager->isPackageActive('products_search');

        return [
            'hasSearch' => $hasSearch,
            'resultsPerPage' => $hasSearch
                ? max(1, (int)$context->site->getSettings()->get('products.search.resultsPerPage', self::DEFAULT_RESULTS_PER_PAGE))
                : 0,
        ];
    }
}
