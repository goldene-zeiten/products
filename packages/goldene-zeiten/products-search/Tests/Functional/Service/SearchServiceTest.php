<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Search\Tests\Functional\Service;

use GoldeneZeiten\Products\Search\Service\SearchService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

final class SearchServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-search',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('search.csv'));
    }

    #[Test]
    #[DataProvider('blankTermProvider')]
    public function blankTermsNeverExecuteAQuery(string $term): void
    {
        $result = $this->subject()->search($term, null, 1, $this->createRequest());

        $this->assertFalse($result->hasSearched());
        $this->assertSame(0, $result->getTotalCount());
        $this->assertSame([], $result->getProducts());
    }

    public static function blankTermProvider(): \Generator
    {
        yield 'blank term' => ['term' => ''];
        yield 'whitespace only term' => ['term' => '   '];
    }

    #[Test]
    #[DataProvider('paginationProvider')]
    public function paginationBehavior(int $resultsPerPage, string $term, int $requestedPage, int $expectedCount, int $expectedCurrentPage, int $expectedTotalPages): void
    {
        $result = $this->subject()->search($term, null, $requestedPage, $this->createRequest($resultsPerPage));

        $this->assertTrue($result->hasSearched());
        $this->assertTrue($result->hasResults());
        $this->assertCount($expectedCount, $result->getProducts());
        $this->assertSame(2, $result->getTotalCount());
        $this->assertSame($expectedTotalPages, $result->getTotalPages());
        $this->assertSame($expectedCurrentPage, $result->getCurrentPage());
    }

    public static function paginationProvider(): \Generator
    {
        yield 'first page' => ['resultsPerPage' => 1, 'term' => 'Shoes', 'requestedPage' => 1, 'expectedCount' => 1, 'expectedCurrentPage' => 1, 'expectedTotalPages' => 2];
        yield 'second page' => ['resultsPerPage' => 1, 'term' => 'Shoes', 'requestedPage' => 2, 'expectedCount' => 1, 'expectedCurrentPage' => 2, 'expectedTotalPages' => 2];
        yield 'page below one clamped to first' => ['resultsPerPage' => 1, 'term' => 'Shoes', 'requestedPage' => 0, 'expectedCount' => 1, 'expectedCurrentPage' => 1, 'expectedTotalPages' => 2];
    }

    #[Test]
    public function noResultsIsReportedDistinctlyFromNeverHavingSearched(): void
    {
        $result = $this->subject()->search('doesnotexist', null, 1, $this->createRequest());

        $this->assertTrue($result->hasSearched());
        $this->assertFalse($result->hasResults());
    }

    private function subject(): SearchService
    {
        return $this->get(SearchService::class);
    }

    private function createRequest(int $resultsPerPage = 20): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['search' => ['resultsPerPage' => $resultsPerPage]]]]);

        return (new ServerRequest('http://example.com/'))->withAttribute('site', $site);
    }
}
