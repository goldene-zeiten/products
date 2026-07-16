<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface;

/**
 * Fixture express-checkout provider for the registry tests: available only for EUR, so the availability
 * filter can be exercised through a real context.
 */
final class FixtureExpressCheckoutProvider implements ExpressCheckoutProviderInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-express';
    }

    public function getLabel(): string
    {
        return 'Fixture Express Checkout';
    }

    public function isAvailable(ExpressCheckoutContext $context): bool
    {
        return strtoupper($context->getCurrency()) === 'EUR';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function getButtonConfiguration(ExpressCheckoutContext $context): array
    {
        return [
            'provider' => 'fixture-express',
            'amount' => $context->getAmount()->getCents(),
            'currency' => $context->getCurrency(),
        ];
    }
}
