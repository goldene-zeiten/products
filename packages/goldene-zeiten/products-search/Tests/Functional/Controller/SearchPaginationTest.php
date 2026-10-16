<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Search\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Search results are paged with the same page links as the catalog's product lists.
 */
final class SearchPaginationTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $coreExtensionsToLoad = [
        'fluid_styled_content',
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-search',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/search_browse_modes.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'settings' => [
                    'products' => [
                        'search' => [
                            'resultsPerPage' => 2,
                        ],
                    ],
                ],
            ]),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products_core/Configuration/TypoScript/setup.typoscript',
                'EXT:products_search/Configuration/TypoScript/setup.typoscript',
                'EXT:products_search/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 200
            }
        ');
    }

    #[Test]
    public function aSearchWithMoreResultsThanFitOnOnePageLinksTheFurtherPages(): void
    {
        $body = $this->search(1);

        $this->assertStringContainsString('class="pagination', $body);
        $this->assertMatchesRegularExpression('#href="[^"]*tx_productssearch_search%5BcurrentPage%5D=3[^"]*"#', $body);
        $this->assertMatchesRegularExpression('#href="[^"]*tx_productssearch_search%5Bterm%5D=Product[^"]*"#', $body);
    }

    #[Test]
    public function aFurtherPageShowsTheNextResults(): void
    {
        $firstPage = $this->search(1);
        $secondPage = $this->search(2);

        $this->assertStringContainsString('>Product 1<', $firstPage);
        $this->assertStringNotContainsString('>Product 1<', $secondPage);
        $this->assertStringContainsString('>Product 3<', $secondPage);
    }

    private function search(int $page): string
    {
        $parameters = '&id=2&tx_productssearch_search[currentPage]=' . $page . '&tx_productssearch_search[term]=Product';
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productssearch_search[currentPage]' => (string)$page,
                'tx_productssearch_search[term]' => 'Product',
                'cHash' => $this->get(CacheHashCalculator::class)->generateForParameters($parameters),
            ])
        );
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }
}
