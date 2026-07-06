<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional;

use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;

/**
 * Base class for functional tests that need a working frontend request,
 * e.g. because production code relies on Extbase's ConfigurationManager
 * (settings, TypoScript) which is only available within a TSFE context.
 *
 * Page rendering (including a minimal `lib.contentElement` anchor for
 * PLUGIN_TYPE_CONTENT_ELEMENT plugins) comes from the `frontend-test`
 * Site Set fixture, not from ad-hoc TypoScript injected per test.
 */
abstract class AbstractFrontendTestCase extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
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
