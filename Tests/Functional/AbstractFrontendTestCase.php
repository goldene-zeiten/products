<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional;

use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;

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
        'goldene-zeiten/products-core',
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
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }
}
