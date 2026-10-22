<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Guide\Collector;

use GoldeneZeiten\Products\Mcp\Guide\PageUrlResolver;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * The variables of the ordering guide: which optional add-ons are installed, so the guide only mentions what the
 * shop can do.
 */
final readonly class OrderingGuideCollector
{
    public function __construct(
        private PackageManager $packageManager,
        private PageUrlResolver $pageUrlResolver
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(McpContext $context): array
    {
        return [
            'hasPersonalization' => $this->packageManager->isPackageActive('products_personalization'),
            'hasVouchers' => $this->packageManager->isPackageActive('products_voucher'),
            'basketUrl' => $this->pageUrlResolver->resolve($context, (int)$context->site->getSettings()->get('products.pids.basketPage', 0)),
        ];
    }
}
