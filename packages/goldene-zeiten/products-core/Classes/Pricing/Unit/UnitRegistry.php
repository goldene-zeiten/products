<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing\Unit;

final class UnitRegistry
{
    /**
     * @var array<string, array{dimension: string, factorToBase: float, referenceUnit: string, referenceAmountInBase: float}>
     */
    private const UNITS = [
        // Mass (base = grams)
        'g' => [
            'dimension' => 'mass',
            'factorToBase' => 1.0,
            'referenceUnit' => 'kg',
            'referenceAmountInBase' => 1000.0,
        ],
        'kg' => [
            'dimension' => 'mass',
            'factorToBase' => 1000.0,
            'referenceUnit' => 'kg',
            'referenceAmountInBase' => 1000.0,
        ],
        'oz' => [
            'dimension' => 'mass',
            'factorToBase' => 28.349523125,
            'referenceUnit' => 'lb',
            'referenceAmountInBase' => 453.59237,
        ],
        'lb' => [
            'dimension' => 'mass',
            'factorToBase' => 453.59237,
            'referenceUnit' => 'lb',
            'referenceAmountInBase' => 453.59237,
        ],

        // Volume (base = millilitres)
        'ml' => [
            'dimension' => 'volume',
            'factorToBase' => 1.0,
            'referenceUnit' => 'l',
            'referenceAmountInBase' => 1000.0,
        ],
        'l' => [
            'dimension' => 'volume',
            'factorToBase' => 1000.0,
            'referenceUnit' => 'l',
            'referenceAmountInBase' => 1000.0,
        ],
        'fl_oz' => [
            'dimension' => 'volume',
            'factorToBase' => 29.5735295625,
            'referenceUnit' => 'gal',
            'referenceAmountInBase' => 3785.411784,
        ],
        'gal' => [
            'dimension' => 'volume',
            'factorToBase' => 3785.411784,
            'referenceUnit' => 'gal',
            'referenceAmountInBase' => 3785.411784,
        ],

        // Length (base = millimetres)
        'mm' => [
            'dimension' => 'length',
            'factorToBase' => 1.0,
            'referenceUnit' => 'm',
            'referenceAmountInBase' => 1000.0,
        ],
        'cm' => [
            'dimension' => 'length',
            'factorToBase' => 10.0,
            'referenceUnit' => 'm',
            'referenceAmountInBase' => 1000.0,
        ],
        'm' => [
            'dimension' => 'length',
            'factorToBase' => 1000.0,
            'referenceUnit' => 'm',
            'referenceAmountInBase' => 1000.0,
        ],
        'in' => [
            'dimension' => 'length',
            'factorToBase' => 25.4,
            'referenceUnit' => 'ft',
            'referenceAmountInBase' => 304.8,
        ],
        'ft' => [
            'dimension' => 'length',
            'factorToBase' => 304.8,
            'referenceUnit' => 'ft',
            'referenceAmountInBase' => 304.8,
        ],

        // Area (base = square-millimetres)
        'm2' => [
            'dimension' => 'area',
            'factorToBase' => 1000000.0,
            'referenceUnit' => 'm2',
            'referenceAmountInBase' => 1000000.0,
        ],
        'ft2' => [
            'dimension' => 'area',
            'factorToBase' => 92903.04,
            'referenceUnit' => 'ft2',
            'referenceAmountInBase' => 92903.04,
        ],
    ];

    public function get(string $unitCode): ?UnitDefinition
    {
        if (!isset(self::UNITS[$unitCode])) {
            return null;
        }

        $config = self::UNITS[$unitCode];
        return new UnitDefinition(
            dimension: $config['dimension'],
            factorToBase: $config['factorToBase'],
            referenceUnit: $config['referenceUnit'],
            referenceAmountInBase: $config['referenceAmountInBase'],
        );
    }
}
