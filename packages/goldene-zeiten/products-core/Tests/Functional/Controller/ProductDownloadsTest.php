<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A product's downloadable files are what a customer pays for, so the product page names them without
 * handing out their location.
 */
final class ProductDownloadsTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    #[Test]
    public function theProductPageNamesTheDownloadsWithoutLinkingThem(): void
    {
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadControllerTest/products_with_downloads.csv');
        $this->get(StorageRepository::class)->createLocalStorage('downloads', 'fileadmin/', 'relative');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:products_core/Configuration/TypoScript/constants.typoscript'],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = ProductsCore
                pluginName = ProductDetail
                vendorName = GoldeneZeiten
            }
        ');
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=10');

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productscore_productdetail[product]' => 10,
                'cHash' => $cHash,
            ])
        );

        $body = (string)$response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('example.pdf', $body);
        $this->assertStringContainsString('Available for download after purchase.', $body);
        $this->assertStringNotContainsString('fileadmin/example.pdf', $body);
    }
}
