<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Pricing;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Pricing\UnitPriceCalculator;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class UnitPriceViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly UnitPriceCalculator $calculator,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('price', 'mixed', 'The price (Money)', true);
        $this->registerArgument('contentAmount', 'float', 'The content amount', true);
        $this->registerArgument('contentUnit', 'string', 'The content unit code', true);
    }

    public function render(): string
    {
        $price = $this->arguments['price'];
        $contentAmount = (float)$this->arguments['contentAmount'];
        $contentUnit = (string)$this->arguments['contentUnit'];

        if (!$price instanceof Money) {
            return '';
        }

        $unitPrice = $this->calculator->calculate($price, $contentAmount, $contentUnit);

        if ($unitPrice === null) {
            return '';
        }

        return sprintf('%s / %s', $unitPrice->price->getDecimalString(), $unitPrice->referenceUnitLabel);
    }
}
