<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Testing;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Proves a site set's settings reach plugin.tx_productscore.settings - the settings Extbase hands every
 * core plugin, and the ones add-on plugins inherit - with the site's own value winning over the default,
 * and that the package's classic static template starts from the very same defaults.
 *
 * A package extends it with its set, its definitions file and its static template files.
 */
abstract class AbstractSiteSettingsMappingTestCase extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    abstract protected static function definitionsFile(): string;

    /**
     * @return string[] Site sets, the products-core set and the settings dump excluded
     */
    abstract protected static function siteSets(): array;

    /**
     * @return array{constants: string[], setup: string[]} The static templates, products-core's excluded
     */
    abstract protected static function staticTemplate(): array;

    protected function setUp(): void
    {
        $this->testExtensionsToLoad = array_merge(['goldene-zeiten/frontend-test'], $this->testExtensionsToLoad);
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
    }

    #[Test]
    public function everySettingReachesThePluginSettingsWithItsDefault(): void
    {
        $this->writeShopSite([]);

        $defaults = SettingsDefinitions::defaults(static::definitionsFile());
        $this->assertNotSame([], $defaults);
        $this->assertSame($defaults, $this->renderedSettings(array_keys($defaults)));
    }

    #[Test]
    public function aSiteSettingOverridesTheDefault(): void
    {
        $overrides = SettingsDefinitions::overrides(static::definitionsFile());
        $this->writeShopSite(SettingsDefinitions::toTree($overrides));

        $this->assertSame(
            array_map(SettingsDefinitions::asConstant(...), $overrides),
            $this->renderedSettings(array_keys($overrides))
        );
    }

    #[Test]
    public function theStaticTemplateStartsFromTheSameDefaults(): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $staticTemplate = static::staticTemplate();
        $this->setUpFrontendRootPage(1, [
            'constants' => array_merge(
                ['EXT:products_core/Configuration/TypoScript/constants.typoscript'],
                $staticTemplate['constants']
            ),
            'setup' => array_merge(
                ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
                $staticTemplate['setup'],
                ['EXT:frontend_test/Configuration/TypoScript/SettingsDump/setup.typoscript']
            ),
        ]);

        $defaults = SettingsDefinitions::staticTemplateDefaults(static::definitionsFile());
        $this->assertSame($defaults, $this->renderedSettings(array_keys($defaults)));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function writeShopSite(array $settings): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => array_merge(
                    ['goldene-zeiten/products-core'],
                    static::siteSets(),
                    ['goldene-zeiten/settings-dump']
                ),
                'settings' => $settings,
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        // The site's TypoScript is cached per site identifier, which every test of the class shares.
        $this->get(CacheManager::class)->getCache('typoscript')->flush();
    }

    /**
     * @param string[] $identifiers
     * @return array<string, string|null>
     */
    private function renderedSettings(array $identifiers): array
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));
        $pluginSettings = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($pluginSettings, (string)$response->getBody());

        $values = [];
        foreach ($identifiers as $identifier) {
            $values[$identifier] = SettingsDefinitions::pluginValue($pluginSettings, $identifier);
        }
        return $values;
    }
}
