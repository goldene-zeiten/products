<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Testing\AbstractSiteSettingsMappingTestCase;

/**
 * The wishlist settings reach the core catalog settings the wishlist plugin inherits.
 */
final class SiteSettingsReachPluginSettingsTest extends AbstractSiteSettingsMappingTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-wishlist',
    ];

    protected static function definitionsFile(): string
    {
        return __DIR__ . '/../../../Configuration/Sets/Wishlist/settings.definitions.yaml';
    }

    protected static function siteSets(): array
    {
        return [
            'goldene-zeiten/products-wishlist',
        ];
    }

    protected static function staticTemplate(): array
    {
        return [
            'constants' => [
                'EXT:products_wishlist/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:products_wishlist/Configuration/TypoScript/setup.typoscript',
            ],
        ];
    }
}
