<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Testing\AbstractSiteSettingsMappingTestCase;

/**
 * The products set's site settings are the shop's configuration; every one of them must reach the
 * settings the core plugins receive.
 */
final class SiteSettingsReachPluginSettingsTest extends AbstractSiteSettingsMappingTestCase
{
    protected static function definitionsFile(): string
    {
        return __DIR__ . '/../../../Configuration/Sets/Products/settings.definitions.yaml';
    }

    protected static function siteSets(): array
    {
        return [];
    }

    protected static function staticTemplate(): array
    {
        return [
            'constants' => [],
            'setup' => [],
        ];
    }
}
