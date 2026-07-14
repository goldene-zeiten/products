<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class SearchControllerTest extends AbstractFunctionalTestCase
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
        'install',
        'fluid_styled_content',
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/search_browse_modes.csv');

        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
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
                'EXT:products_core/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
        ');
    }

    #[Test]
    public function searchActionWithDefaultTextModeStillWorksAndSkipsFacetedBrowsing(): void
    {
        $request = $this->contentRequest(200);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Search', $body);
        // Proves mode dispatch actually skips the faceted-browse branch for "text" mode,
        // rather than both branches happening to render harmlessly together.
        $this->assertStringNotContainsString('list-unstyled', $body);
    }

    #[Test]
    public function searchActionWithFirstLetterModeGroupsByFirstLetter(): void
    {
        $request = $this->contentRequest(201);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        // All products start with "Product" so they should be under "P"
        $this->assertStringContainsString('<h3>P</h3>', $body);
        $this->assertStringContainsString('Product 1', $body);
        $this->assertStringContainsString('Product 2', $body);
        $this->assertStringContainsString('Product 3', $body);
        $this->assertStringContainsString('Product 4', $body);
        $this->assertStringContainsString('Product 5', $body);
        $this->assertStringContainsString('Product 6', $body);
    }

    #[Test]
    public function searchActionWithYearModeGroupsProductsByCreationYear(): void
    {
        // shop.csv: Product 1 has crdate 100000 (1970), Products 2-6 all fall in 2025.
        $request = $this->contentRequest(202);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('<h3>1970</h3>', $body);
        $this->assertStringContainsString('<h3>2025</h3>', $body);
        $yearHeadingPosition1970 = strpos($body, '<h3>1970</h3>');
        $yearHeadingPosition2025 = strpos($body, '<h3>2025</h3>');
        $productOnePosition = strpos($body, 'Product 1');
        // 2025 (the more recent year) must sort first - groupByYear() orders descending.
        $this->assertLessThan($yearHeadingPosition1970, $yearHeadingPosition2025);
        // Product 1 (crdate year 1970) must be listed after the "1970" heading, which is the
        // last group - proves it's grouped under the right year, not just present anywhere.
        $this->assertGreaterThan($yearHeadingPosition1970, $productOnePosition);
    }

    #[Test]
    public function searchActionWithFieldModeGroupsProductsByExactTitleValue(): void
    {
        // Every product has a unique title, so grouping by the exact "title" field value
        // produces one singleton group per product - each rendered under its own heading.
        $request = $this->contentRequest(203);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('<h3>Product 1</h3>', $body);
        $this->assertStringContainsString('<h3>Product 6</h3>', $body);
    }

    #[Test]
    public function searchActionWithLastEntriesModeOrdersByMostRecentFirst(): void
    {
        // shop.csv crdate ordering (newest first): 6, 5, 4, 3, 2, 1.
        $request = $this->contentRequest(204);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 6', $body);
        $this->assertStringContainsString('Product 1', $body);
        $this->assertLessThan(strpos($body, 'Product 1'), strpos($body, 'Product 6'));
        // lastentries groups under a single, label-less bucket - no <h3> heading rendered.
        $this->assertStringNotContainsString('<h3>', $body);
    }

    #[Test]
    public function searchActionWithArticlesTargetGroupsArticlesNotProducts(): void
    {
        // shop.csv articles: "Article 1"/"Article 2" (product 1), "Article 3" (product 2) - all under "A".
        $request = $this->contentRequest(205);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('<h3>A</h3>', $body);
        $this->assertStringContainsString('Article 1', $body);
        $this->assertStringContainsString('Article 2', $body);
        $this->assertStringContainsString('Article 3', $body);
        $this->assertStringNotContainsString('Product 1', $body);
    }

    #[Test]
    public function searchActionWithCategoriesTargetGroupsCategoriesNotProducts(): void
    {
        // shop.csv has a single category, "Category 1".
        $request = $this->contentRequest(206);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('<h3>C</h3>', $body);
        $this->assertStringContainsString('Category 1', $body);
        $this->assertStringNotContainsString('Product 1', $body);
    }

    /**
     * The keyfield fixture is the only data carrying item numbers (KF-A/KF-B/KF-C), so the options
     * are those item numbers while the results below the form show titles - which keeps the two
     * regions distinguishable in the rendered body.
     */
    #[Test]
    public function searchActionWithKeyFieldModeOffersEveryValueOfTheFieldAsAnOption(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/keyfield_products.csv');
        $request = $this->contentRequest(207);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('multiple="multiple"', $body);
        $this->assertStringContainsString('<option value="KF-A">KF-A</option>', $body);
        $this->assertStringContainsString('<option value="KF-B">KF-B</option>', $body);
        $this->assertStringContainsString('<option value="KF-C">KF-C</option>', $body);
        // Nothing is ticked yet, so no result rows are rendered.
        $this->assertStringNotContainsString('Keyfield Alpha', $body);
    }

    #[Test]
    public function searchActionWithKeyFieldModeReturnsTheUnionOfTheTickedValues(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/keyfield_products.csv');
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 207
            }
        ');

        $queryParameters = [
            'tx_productscore_search[keywords][0]' => 'KF-A',
            'tx_productscore_search[keywords][1]' => 'KF-C',
        ];
        // RFC3986 encoding required; RFC1738 "+" breaks cHash for values with spaces.
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&' . http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986)
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters(array_merge($queryParameters, ['cHash' => $cHash]));
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Keyfield Alpha', $body);
        $this->assertStringContainsString('Keyfield Gamma', $body);
        $this->assertStringNotContainsString('Keyfield Beta', $body);
    }

    /**
     * Renders the real tt_content row with the given uid through the normal CType dispatch,
     * so currentContentObject carries that row's actual tx_products_search_browse_mode -
     * a hardcoded "page.10 = USER" plugin call never populates currentContentObject and
     * can't exercise per-element mode configuration.
     */
    private function contentRequest(int $contentUid): InternalRequest
    {
        $this->addTypoScriptToTemplateRecord(1, sprintf('
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = %d
            }
        ', $contentUid));

        return new InternalRequest('http://localhost/shop');
    }
}
