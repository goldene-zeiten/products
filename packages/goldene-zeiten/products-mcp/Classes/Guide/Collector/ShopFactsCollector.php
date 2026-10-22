<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Guide\Collector;

use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalSettingsFactory;
use GoldeneZeiten\Products\Mcp\Guide\PageUrlResolver;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * The variables of the shop facts: where the shop is, in which currency and with or without VAT it prices, and
 * who the trader is.
 */
final readonly class ShopFactsCollector
{
    public function __construct(
        private PageUrlResolver $pageUrlResolver,
        private WithdrawalSettingsFactory $withdrawalSettingsFactory
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(McpContext $context): array
    {
        $settings = $context->site->getSettings();

        return [
            'baseUrl' => $this->pageUrlResolver->base($context),
            'currency' => (string)$settings->get('products.pricing.currency', 'EUR'),
            'grossPrices' => (string)$settings->get('products.pricing.mode', 'gross') === 'gross',
            'trader' => $this->withdrawalSettingsFactory->fromSettings($settings)->trader,
            'vatId' => (string)$settings->get('products.invoice.vatId', ''),
            'taxNumber' => (string)$settings->get('products.invoice.taxNumber', ''),
        ];
    }
}
