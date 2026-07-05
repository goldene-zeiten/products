<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Format;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class MoneyViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'mixed', 'The money object or cents to format', true);
        $this->registerArgument('currency', 'string', 'The currency symbol', false, '€');
    }

    public function render(): string
    {
        $value = $this->arguments['value'];
        $currency = $this->arguments['currency'];

        if (!$value instanceof Money) {
            if (is_numeric($value)) {
                $value = Money::fromCents((int)$value);
            } else {
                return '';
            }
        }

        return $value->toDecimalString() . ' ' . $currency;
    }
}
