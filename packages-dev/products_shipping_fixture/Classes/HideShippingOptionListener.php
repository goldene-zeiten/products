<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ShippingFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Event\ShippingOptionsCollectedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Hides the option with the configured key from the offered shipping options, as an integrator would for
 * a customer group. Inactive while the key is empty.
 */
#[AsEventListener]
final class HideShippingOptionListener
{
    public static string $hiddenOptionKey = '';

    public function __invoke(ShippingOptionsCollectedEvent $event): void
    {
        if (self::$hiddenOptionKey === '') {
            return;
        }
        $event->setOptions(array_values(array_filter(
            $event->getOptions(),
            static fn(ShippingOption $option): bool => $option->getKey() !== self::$hiddenOptionKey
        )));
    }
}
