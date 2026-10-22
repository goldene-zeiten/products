<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Resource;

use GoldeneZeiten\Products\Mcp\Guide\Collector\CheckoutGuideCollector;
use GoldeneZeiten\Products\Mcp\Guide\GuideRenderer;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Explains to the assistant the steps of the checkout, the payment methods and the delivery countries.
 */
final class CheckoutGuideResource extends AbstractGuideResource
{
    public function __construct(
        GuideRenderer $renderer,
        private readonly CheckoutGuideCollector $collector
    ) {
        parent::__construct($renderer);
    }

    public function getUri(): string
    {
        return 'shop://guide/checkout';
    }

    public function getName(): string
    {
        return 'How to check out';
    }

    public function getDescription(): string
    {
        return 'The checkout steps, payment methods, shipping, delivery countries and the legal pages.';
    }

    protected function getTemplateName(): string
    {
        return 'Checkout';
    }

    protected function collectVariables(McpContext $context): array
    {
        return $this->collector->collect($context);
    }
}
