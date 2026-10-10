<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Search\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Testing\AbstractSiteSettingsMappingTestCase;

/**
 * The search settings reach the core catalog settings the search plugin inherits.
 */
final class SiteSettingsReachPluginSettingsTest extends AbstractSiteSettingsMappingTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-search',
    ];

    protected static function definitionsFile(): string
    {
        return __DIR__ . '/../../../Configuration/Sets/Search/settings.definitions.yaml';
    }

    protected static function siteSets(): array
    {
        return [
            'goldene-zeiten/products-search',
        ];
    }

    protected static function staticTemplate(): array
    {
        return [
            'constants' => [
                'EXT:products_search/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:products_search/Configuration/TypoScript/setup.typoscript',
            ],
        ];
    }
}
