<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Tells the assistant which shop it is connected to.
 */
final class ShopInfoTool implements McpToolInterface
{
    public function getName(): string
    {
        return 'shop_info';
    }

    public function getDescription(): string
    {
        return 'Returns the name, the base URL and the currency of the shop.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $site = $context->site;
        $title = (string)($site->getConfiguration()['websiteTitle'] ?? '');

        return [
            'name' => $title !== '' ? $title : $site->getIdentifier(),
            'baseUrl' => (string)$site->getBase(),
            'currency' => (string)$site->getSettings()->get('products.pricing.currency', 'EUR'),
        ];
    }
}
