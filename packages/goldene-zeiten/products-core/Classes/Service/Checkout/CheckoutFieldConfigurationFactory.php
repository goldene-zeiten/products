<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Checkout;

use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutFieldConfiguration;
use GoldeneZeiten\Products\Core\Domain\Enum\FieldRequirement;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;

/**
 * Reads the shop's checkout field configuration (`products.checkout.*`) from the site settings.
 */
final class CheckoutFieldConfigurationFactory
{
    /**
     * @var array<string, FieldRequirement>
     */
    private const DEFAULT_REQUIREMENTS = [
        'salutation' => FieldRequirement::Optional,
        'company' => FieldRequirement::Optional,
        'telephone' => FieldRequirement::Optional,
        'vatId' => FieldRequirement::Hidden,
    ];

    public function fromSite(?Site $site): CheckoutFieldConfiguration
    {
        $settings = $site?->getSettings();
        return new CheckoutFieldConfiguration(
            $this->requirements($settings),
            array_map(strtoupper(...), $this->list($settings, 'products.checkout.allowedCountries', '')),
            $this->list($settings, 'products.checkout.salutations', 'mr,ms,diverse'),
            (bool)($settings?->get('products.checkout.desiredDeliveryDate', false) ?? false),
        );
    }

    /**
     * An unknown value falls back to the field's default rather than hiding a field by accident.
     *
     * @return array<string, FieldRequirement>
     */
    private function requirements(?SiteSettings $settings): array
    {
        $requirements = [];
        foreach (self::DEFAULT_REQUIREMENTS as $field => $default) {
            $value = (string)($settings?->get('products.checkout.fields.' . $field, $default->value) ?? $default->value);
            $requirements[$field] = FieldRequirement::tryFrom($value) ?? $default;
        }
        return $requirements;
    }

    /**
     * @return string[]
     */
    private function list(?SiteSettings $settings, string $path, string $default): array
    {
        $value = (string)($settings?->get($path, $default) ?? $default);
        return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn(string $item): bool => $item !== ''));
    }
}
