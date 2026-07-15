<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Testing;

use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;

/**
 * Base class for tests that render a real frontend request.
 *
 * A package whose frontend test needs its own extension declares it in $testExtensionsToLoad - the
 * mandatory ones are added by {@see AbstractFunctionalTestCase::setUp()} - and adds its site set to the
 * ones the shop site is built from by overriding {@see siteSetDependencies()}.
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

    /**
     * Provides the page/TypoScript scaffolding a rendered frontend needs.
     */
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopFrontend();
    }

    /**
     * Site sets the shop site is built from. Extended by a package that ships one of its own.
     *
     * @return string[]
     */
    protected function siteSetDependencies(): array
    {
        return [
            'goldene-zeiten/products-core',
            'goldene-zeiten/frontend-test',
        ];
    }

    protected function setUpShopFrontend(int $rootPageUid = 1): void
    {
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration($rootPageUid, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }
}
