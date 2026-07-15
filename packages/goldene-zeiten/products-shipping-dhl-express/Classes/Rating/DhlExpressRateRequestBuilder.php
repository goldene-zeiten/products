<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;

/**
 * Builds the DHL Express "GET /rates" query parameters from the basket context and the resolved
 * configuration. The shop rates by country and postcode; DHL also wants a city and package dimensions, so
 * the postcode is sent as the destination city (DHL geocodes by postcode + country regardless) and a
 * small-parcel default dimension is used. Integrators needing real dimensions adjust them via
 * {@see \GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressRateRequestEvent}.
 */
final class DhlExpressRateRequestBuilder
{
    private const DEFAULT_LENGTH = 20;
    private const DEFAULT_WIDTH = 15;
    private const DEFAULT_HEIGHT = 10;
    private const GRAMS_PER_KILOGRAM = 1000.0;
    private const GRAMS_PER_POUND = 453.59237;

    /**
     * @return array<string, string>
     */
    public function build(ShippingContext $context, DhlExpressConfiguration $configuration): array
    {
        $parameters = [
            'accountNumber' => $configuration->accountNumber,
            'originCountryCode' => strtoupper($configuration->originCountryCode),
            'originCityName' => $configuration->originCityName,
            'originPostalCode' => $configuration->originPostCode,
            'destinationCountryCode' => strtoupper($context->getCountryCode()),
            'destinationCityName' => $context->getPostCode(),
            'destinationPostalCode' => $context->getPostCode(),
            'weight' => $this->weight($context->getTotalWeight(), $configuration->weightUnit),
            'length' => (string)self::DEFAULT_LENGTH,
            'width' => (string)self::DEFAULT_WIDTH,
            'height' => (string)self::DEFAULT_HEIGHT,
            'plannedShippingDate' => $this->plannedShippingDate(),
            'isCustomsDeclarable' => $this->isCustomsDeclarable($context, $configuration) ? 'true' : 'false',
            'unitOfMeasurement' => $configuration->weightUnit,
        ];

        return array_filter($parameters, static fn(string $value): bool => $value !== '');
    }

    private function weight(int $grams, string $unit): string
    {
        $divisor = $unit === 'imperial' ? self::GRAMS_PER_POUND : self::GRAMS_PER_KILOGRAM;

        // DHL rejects a zero weight; a basket without maintained weights still needs a positive figure.
        return number_format(max(0.1, $grams / $divisor), 3, '.', '');
    }

    private function plannedShippingDate(): string
    {
        return (new \DateTimeImmutable('now'))->modify('+1 day')->format('Y-m-d');
    }

    private function isCustomsDeclarable(ShippingContext $context, DhlExpressConfiguration $configuration): bool
    {
        return strtoupper($configuration->originCountryCode) !== strtoupper($context->getCountryCode());
    }
}
