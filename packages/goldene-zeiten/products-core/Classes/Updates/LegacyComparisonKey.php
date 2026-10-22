<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

/**
 * Compares legacy values the way the legacy shop's collation did: "Rosègold" and "roségold" are the same
 * value, so the key is lower case with the accents dropped.
 */
final class LegacyComparisonKey
{
    public static function of(string $value): string
    {
        $decomposed = \Normalizer::normalize(mb_strtolower($value), \Normalizer::FORM_D);
        return preg_replace('/\p{Mn}+/u', '', $decomposed === false ? mb_strtolower($value) : $decomposed) ?? $value;
    }
}
