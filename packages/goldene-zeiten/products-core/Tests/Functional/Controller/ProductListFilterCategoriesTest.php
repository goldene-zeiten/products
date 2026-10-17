<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A list shows the products of any of its categories; filter categories narrow that down to the products that
 * are in every one of them as well.
 */
final class ProductListFilterCategoriesTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductListFilterCategoriesTest/filtered_list.csv');
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
     * @param string[] $expectedProducts
     * @param string[] $unexpectedProducts
     */
    #[Test]
    #[DataProvider('listProvider')]
    public function theListShowsTheProductsOfItsCategoriesInEveryFilterCategory(int $contentUid, array $expectedProducts, array $unexpectedProducts): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update('tt_content', ['hidden' => 1], ['pid' => 2]);
        $connection->update('tt_content', ['hidden' => 0], ['uid' => $contentUid]);

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        foreach ($expectedProducts as $product) {
            $this->assertStringContainsString('>' . $product . '<', $body);
        }
        foreach ($unexpectedProducts as $product) {
            $this->assertStringNotContainsString('>' . $product . '<', $body);
        }
    }

    public static function listProvider(): \Generator
    {
        yield 'a filter category narrows the subtree of the list category' => [
            'contentUid' => 710,
            'expectedProducts' => ['Champagne engagement ring'],
            'unexpectedProducts' => ['Plain engagement ring', 'Champagne pendant'],
        ];
        yield 'without filter categories the whole subtree is listed' => [
            'contentUid' => 711,
            'expectedProducts' => ['Champagne engagement ring', 'Plain engagement ring'],
            'unexpectedProducts' => ['Champagne pendant'],
        ];
    }
}
