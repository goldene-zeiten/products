<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Checkout;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The fields an order cannot be shipped or invoiced without. Shared by the address form's validation and
 * by order placement, so a customer who skips the form is held to the same rule.
 */
final class AddressCompletenessChecker
{
    /**
     * @return string[] Names of the Address properties that are missing or invalid.
     */
    public function getInvalidFields(Address $address, bool $emailRequired = true): array
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

        return $invalidFields;
    }

    public function isComplete(Address $address, bool $emailRequired = true): bool
    {
        return $this->getInvalidFields($address, $emailRequired) === [];
    }
}
