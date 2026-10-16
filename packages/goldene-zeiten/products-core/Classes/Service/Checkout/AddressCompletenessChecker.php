<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Checkout;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutFieldConfiguration;
use GoldeneZeiten\Products\Core\Domain\Enum\FieldRequirement;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The fields an order cannot be shipped or invoiced without. Shared by the address form's validation and
 * by order placement, so a customer who skips the form is held to the same rule. With the shop's
 * {@see CheckoutFieldConfiguration} it also enforces the required contact fields, the allowed countries
 * and the salutation choices.
 */
final class AddressCompletenessChecker
{
    /**
     * @return string[] Names of the Address properties that are missing or invalid.
     */
    public function getInvalidFields(Address $address, bool $emailRequired = true, ?CheckoutFieldConfiguration $fields = null): array
    {
        $values = [
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'zip' => $address->getZip(),
            'city' => $address->getCity(),
            'country' => $address->getCountry(),
        ];
        $invalidFields = [];
        if ($emailRequired && !GeneralUtility::validEmail(trim($address->getEmail()))) {
            $invalidFields[] = 'email';
        }
        foreach ($values as $field => $value) {
            if (trim($value) === '') {
                $invalidFields[] = $field;
            }
        }
        if ($fields === null) {
            return $invalidFields;
        }
        return array_values(array_unique(array_merge($invalidFields, $this->getInvalidConfiguredFields($address, $fields))));
    }

    public function isComplete(Address $address, bool $emailRequired = true, ?CheckoutFieldConfiguration $fields = null): bool
    {
        return $this->getInvalidFields($address, $emailRequired, $fields) === [];
    }

    /**
     * @return string[]
     */
    private function getInvalidConfiguredFields(Address $address, CheckoutFieldConfiguration $fields): array
    {
        $values = [
            'salutation' => $address->getSalutation(),
            'company' => $address->getCompany(),
            'telephone' => $address->getTelephone(),
            'vatId' => $address->getVatId(),
        ];
        $invalidFields = [];
        foreach ($values as $field => $value) {
            if ($fields->requirementOf($field) === FieldRequirement::Required && trim($value) === '') {
                $invalidFields[] = $field;
            }
        }
        if ($address->getSalutation() !== '' && !in_array($address->getSalutation(), $fields->getSalutations(), true)) {
            $invalidFields[] = 'salutation';
        }
        $allowedCountries = $fields->getAllowedCountries();
        if ($allowedCountries !== [] && $address->getCountry() !== '' && !in_array(strtoupper($address->getCountry()), $allowedCountries, true)) {
            $invalidFields[] = 'country';
        }
        return $invalidFields;
    }
}
