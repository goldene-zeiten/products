<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * With EXT:seo installed, products appear in the XML sitemap and an article URL without texts of its own
 * names the product's URL as canonical.
 */
final class ProductSeoIntegrationTest extends AbstractFrontendTestCase
{
    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductSeoTest/products_with_seo.csv');
        // EXT:seo only generates a canonical URL inside a page tree whose root is marked as site root.
        $this->getConnectionPool()->getConnectionForTable('pages')->update('pages', ['is_siteroot' => 1], ['uid' => 1]);
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => [...$this->siteSetDependencies(), 'typo3/seo-sitemap'],
                'settings' => [
                    'products' => [
                        'pids' => [
                            'detailPage' => 2,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    public function theProductSitemapListsTheIndexableProducts(): void
    {
        // The sitemap URLs differ between the core majors, so the products sitemap is taken from the index.
        $index = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))->withQueryParameters(['type' => '1533906435'])
        )->getBody();
        $this->assertSame(1, preg_match('#<loc>([^<]*sitemap[^<]*products[^<]*)</loc>#', $index, $matches));
        $productsSitemapUrl = html_entity_decode($matches[1] ?? '');
        $response = $this->executeFrontendSubRequest(new InternalRequest(
            str_starts_with($productsSitemapUrl, 'http') ? $productsSitemapUrl : 'http://localhost' . $productsSitemapUrl
        ));
        $body = (string)$response->getBody();

        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString('plain-ring', $body);
        $this->assertStringContainsString('wedding-ring', $body);
        $this->assertStringNotContainsString('engagement-ring', $body);
    }

    #[Test]
    public function anArticleWithoutTextsOfItsOwnPointsToTheProductAsCanonical(): void
    {
        $body = $this->renderDetail([
            'tx_productscore_productdetail[product]' => '3',
            'tx_productscore_productdetail[selectedArticle]' => '4',
        ]);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/shop/wedding-ring">', $body);
    }

    #[Test]
    public function anArticleWithTextsOfItsOwnIsItsOwnCanonical(): void
    {
        $body = $this->renderDetail([
            'tx_productscore_productdetail[product]' => '3',
            'tx_productscore_productdetail[selectedArticle]' => '3',
        ]);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/shop/gold-750">', $body);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function renderDetail(array $parameters): string
    {
        $hashString = '&id=2';
        foreach ($parameters as $name => $value) {
            $hashString .= '&' . $name . '=' . $value;
        }
        $parameters['cHash'] = $this->get(CacheHashCalculator::class)->generateForParameters($hashString);
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/shop'))->withQueryParameters($parameters));
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }
}
