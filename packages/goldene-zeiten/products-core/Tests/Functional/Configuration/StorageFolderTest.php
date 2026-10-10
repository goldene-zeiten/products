<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The storage folder site setting is where the catalogue plugins read their records from, without any
 * further TypoScript.
 */
final class StorageFolderTest extends AbstractFrontendTestCase
{
    #[Test]
    public function theProductListReadsTheCatalogueFromTheConfiguredStorageFolder(): void
    {
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/StorageFolderTest/product_list_content.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'pids' => [
                            'storageFolder' => 2,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/catalogue'))->getBody();

        $this->assertStringContainsString('Product 1', $body);
    }
}
