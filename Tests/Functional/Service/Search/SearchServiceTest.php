<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Search;

use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Search\SearchService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class SearchServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/search.csv');
    }

    #[Test]
    public function aBlankTermNeverExecutesAQuery(): void
    {
        $result = $this->subject()->search('', null, 1);

        self::assertFalse($result->hasSearched());
        self::assertSame(0, $result->getTotalCount());
        self::assertSame([], $result->getProducts());
    }

    #[Test]
    public function aWhitespaceOnlyTermIsTreatedAsBlank(): void
    {
        self::assertFalse($this->subject()->search('   ', null, 1)->hasSearched());
    }

    #[Test]
    public function resultsAreSplitAcrossPagesAccordingToTheSiteSetting(): void
    {
        $result = $this->subject(resultsPerPage: 1)->search('Shoes', null, 1);

        self::assertTrue($result->hasSearched());
        self::assertTrue($result->hasResults());
        self::assertCount(1, $result->getProducts());
        self::assertSame(2, $result->getTotalCount());
        self::assertSame(2, $result->getTotalPages());
        self::assertSame(1, $result->getCurrentPage());
    }

    #[Test]
    public function theSecondPageReturnsTheRemainingResult(): void
    {
        $result = $this->subject(resultsPerPage: 1)->search('Shoes', null, 2);

        self::assertCount(1, $result->getProducts());
        self::assertSame(2, $result->getCurrentPage());
    }

    #[Test]
    public function aPageBelowOneIsClampedToTheFirstPage(): void
    {
        $result = $this->subject(resultsPerPage: 1)->search('Shoes', null, 0);

        self::assertSame(1, $result->getCurrentPage());
    }

    #[Test]
    public function noResultsIsReportedDistinctlyFromNeverHavingSearched(): void
    {
        $result = $this->subject()->search('doesnotexist', null, 1);

        self::assertTrue($result->hasSearched());
        self::assertFalse($result->hasResults());
    }

    private function subject(int $resultsPerPage = 20): SearchService
    {
        return new SearchService($this->get(ProductRepository::class), $this->fakeConfigurationManager($resultsPerPage));
    }

    private function fakeConfigurationManager(int $resultsPerPage): ConfigurationManagerInterface
    {
        return new class ($resultsPerPage) implements ConfigurationManagerInterface {
            public function __construct(
                private readonly int $resultsPerPage
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['search' => ['resultsPerPage' => $this->resultsPerPage]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }
}
