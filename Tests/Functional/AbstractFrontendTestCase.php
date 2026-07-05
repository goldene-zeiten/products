<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional;

use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for functional tests that need a working frontend request,
 * e.g. because production code relies on Extbase's ConfigurationManager
 * (settings, TypoScript) which is only available within a TSFE context.
 *
 * Page rendering (including a minimal `lib.contentElement` anchor for
 * PLUGIN_TYPE_CONTENT_ELEMENT plugins) comes from the `frontend-test`
 * Site Set fixture, not from ad-hoc TypoScript injected per test.
 */
abstract class AbstractFrontendTestCase extends FunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    /**
     * `core`, `backend`, `frontend`, `extbase` and `fluid` are already part of
     * `FunctionalTestCase::$defaultCoreExtensionsToLoad` and must not be
     * repeated here. `install` is an additional hard `composer.json` require
     * of this extension and therefore needs to be loaded explicitly.
     */
    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/frontend-test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopFrontend();
    }

    private function setUpShopFrontend(int $rootPageUid = 1): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration($rootPageUid, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }
}
