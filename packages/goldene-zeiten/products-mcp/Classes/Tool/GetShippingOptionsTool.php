<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Shipping\ShippingQuoteService;
use GoldeneZeiten\Products\Mcp\Catalog\ShippingBasketFactory;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Quotes the shipping options and their cost for a destination country and a list of items.
 */
final readonly class GetShippingOptionsTool implements McpToolInterface
{
    public function __construct(
        private ShippingQuoteService $shippingQuoteService,
        private ShippingBasketFactory $basketFactory,
        private ProductsConfigurationFactory $configurationFactory
    ) {}

    public function getName(): string
    {
        return 'get_shipping_options';
    }

    public function getDescription(): string
    {
        return 'Quotes the shipping options and their cost for a destination country and the items to ship. '
            . 'Without items, the options for an empty basket are quoted, which can differ from those of a '
            . 'real basket because carriers decide on weight and value.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'country' => ['type' => 'string', 'description' => 'Destination country, ISO 3166-1 alpha-2 such as DE.'],
                'items' => [
                    'type' => 'array',
                    'description' => 'The items to ship.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product' => ['type' => 'integer', 'description' => 'Uid of the product.'],
                            'article' => ['type' => 'integer', 'description' => 'Uid of the variant, for a product with variants.'],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                        'required' => ['product'],
                    ],
                ],
            ],
            'required' => ['country'],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $country = strtoupper(trim((string)($arguments['country'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw new ToolFailedException('The country has to be an ISO 3166-1 alpha-2 code such as DE.', 1792710234);
        }
        $configuration = $this->configurationFactory->createFromSite($context->site);
        $shipping = $this->basketFactory->create($arguments['items'] ?? [], $country, $context);

        return [
            'country' => $country,
            'currency' => $configuration->getCurrency(),
            'goodsTotal' => $shipping->getGoodsTotal()->getDecimalString(),
            'options' => array_map(
                static fn(ShippingOption $option): array => [
                    'key' => $option->getKey(),
                    'label' => $option->getLabel(),
                    'cost' => $option->getCost()->getDecimalString(),
                    'deliveryEstimate' => $option->getDeliveryEstimate(),
                ],
                $this->shippingQuoteService->getAvailableOptions($configuration, $shipping)
            ),
        ];
    }
}
