<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The manufacturer list shows the manufacturers that have a visible product in any of its categories and in
 * every one of its filter categories.
 */
final class ManufacturerListTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ManufacturerListTest/manufacturers.csv');
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
    }

    /**
     * @param string[] $expectedManufacturers
     * @param string[] $unexpectedManufacturers
     */
    #[Test]
    #[DataProvider('listProvider')]
    public function theListShowsTheManufacturersOfTheMatchingProducts(int $contentUid, array $expectedManufacturers, array $unexpectedManufacturers): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update('tt_content', ['hidden' => 1], ['pid' => 2]);
        $connection->update('tt_content', ['hidden' => 0], ['uid' => $contentUid]);

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        foreach ($expectedManufacturers as $manufacturer) {
            $this->assertStringContainsString('>' . $manufacturer . '<', $body);
        }
        foreach ($unexpectedManufacturers as $manufacturer) {
            $this->assertStringNotContainsString($manufacturer, $body);
        }
    }

    public static function listProvider(): \Generator
    {
        yield 'a filter category narrows the manufacturers of the category' => [
            'contentUid' => 810,
            'expectedManufacturers' => ['Alpha Rings'],
            'unexpectedManufacturers' => ['Beta Rings', 'Gamma Jewels'],
        ];
        yield 'without filter categories every manufacturer of the category is listed' => [
            'contentUid' => 811,
            'expectedManufacturers' => ['Alpha Rings', 'Beta Rings'],
            'unexpectedManufacturers' => ['Gamma Jewels'],
        ];
        yield 'without categories every manufacturer with a visible product is listed' => [
            'contentUid' => 812,
            'expectedManufacturers' => ['Alpha Rings', 'Beta Rings', 'Gamma Jewels'],
            'unexpectedManufacturers' => ['Hidden Maker', 'Ghost Maker', 'Idle Maker'],
        ];
    }

    #[Test]
    public function aManufacturerLinksToItsWebsiteWhenItHasNoPage(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update('tt_content', ['hidden' => 1], ['pid' => 2]);
        $connection->update('tt_content', ['hidden' => 0], ['uid' => 812]);

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        $this->assertStringContainsString('<a href="https://alpha.example"', $body);
    }

    #[Test]
    public function theListIsSortedByTheShownNameCaseInsensitively(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_manufacturer');
        $connection->update('tx_products_domain_model_manufacturer', ['name' => 'Cammilli Firenze', 'brand_name' => 'Zeta Brand'], ['uid' => 1]);
        $connection->update('tx_products_domain_model_manufacturer', ['name' => 'Zebra Ltd', 'brand_name' => 'beta Brand'], ['uid' => 2]);
        $connection->update('tx_products_domain_model_manufacturer', ['name' => 'Alpha Ltd', 'brand_name' => 'Ärger Brand'], ['uid' => 3]);
        $contentConnection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $contentConnection->update('tt_content', ['hidden' => 1], ['pid' => 2]);
        $contentConnection->update('tt_content', ['hidden' => 0], ['uid' => 812]);

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        $this->assertMatchesRegularExpression('/Ärger Brand.*beta Brand.*Zeta Brand/s', $body);
    }
}
