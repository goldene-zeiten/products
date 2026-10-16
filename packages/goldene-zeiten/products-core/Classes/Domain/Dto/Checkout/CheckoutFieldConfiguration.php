<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Core\Domain\Enum\FieldRequirement;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The checkout fields a shop offers beyond the always-required address: the optional contact fields
 * (salutation, company, telephone, VAT id), the allowed countries, the salutation choices and the desired
 * delivery date. Built from the site settings by {@see \GoldeneZeiten\Products\Core\Service\Checkout\CheckoutFieldConfigurationFactory}.
 */
#[Exclude]
final readonly class CheckoutFieldConfiguration
{
    /**
     * @param array<string, FieldRequirement> $fields
     * @param string[] $allowedCountries ISO 3166-1 alpha-2 codes; empty allows every country
     * @param string[] $salutations
     */
    public function __construct(
        private array $fields,
        private array $allowedCountries,
        private array $salutations,
        private bool $desiredDeliveryDateEnabled,
    ) {}

    public function requirementOf(string $field): FieldRequirement
    {
        return $this->fields[$field] ?? FieldRequirement::Hidden;
    }

    /**
     * The rules for a separate delivery address: the contact fields required from the customer are
     * asked once, on the billing address, so none of them is required here.
     */
    public function forDeliveryAddress(): self
    {
        return new self(
            array_map(
                static fn(FieldRequirement $requirement): FieldRequirement => $requirement === FieldRequirement::Required ? FieldRequirement::Optional : $requirement,
                $this->fields
            ),
            $this->allowedCountries,
            $this->salutations,
            $this->desiredDeliveryDateEnabled,
        );
    }

    /**
     * @return array<string, string> field => requirement value, for templates
     */
    public function getFieldRequirements(): array
    {
        return array_map(static fn(FieldRequirement $requirement): string => $requirement->value, $this->fields);
    }

    /**
     * @return string[]
     */
    public function getAllowedCountries(): array
    {
        return $this->allowedCountries;
    }

    /**
     * @return string[]
     */
    public function getSalutations(): array
    {
        return $this->salutations;
    }

    public function isDesiredDeliveryDateEnabled(): bool
    {
        return $this->desiredDeliveryDateEnabled;
    }
}
