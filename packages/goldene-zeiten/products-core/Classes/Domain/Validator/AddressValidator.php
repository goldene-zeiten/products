<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Validator;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Service\Checkout\AddressCompletenessChecker;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validates the checkout's billing address argument, reporting each missing or invalid field on its own
 * property so the address form can show the message next to the field.
 */
final class AddressValidator extends AbstractValidator
{
    public function __construct(
        private readonly AddressCompletenessChecker $addressCompletenessChecker
    ) {}

    protected function isValid(mixed $value): void
    {
        if (!$value instanceof Address) {
            return;
        }
        foreach ($this->addressCompletenessChecker->getInvalidFields($value) as $field) {
            $this->addErrorForProperty(
                $field,
                $this->translateErrorMessage('checkout_address_error_' . $field, 'ProductsCore'),
                1791663369
            );
        }
    }
}
