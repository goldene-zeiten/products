<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ProductRepositorySearchTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('search.csv'));
    }

    /**
     * @param string[] $expectedTitles
     */
    #[Test]
    #[DataProvider('searchTermMatchesProvider')]
    public function searchTermMatches(string $term, array $expectedTitles): void
    {
        $subject = $this->get(ProductRepository::class);

        $this->assertSame($expectedTitles, $this->titles($subject->search($term, null, 0, 10)));
    }

    public static function searchTermMatchesProvider(): \Generator
    {
        yield 'matches by title' => [
            'term' => 'Shoes',
            'expectedTitles' => ['Red Shoes', 'Blue Shoes'],
        ];

        yield 'matches by item number' => [
            'term' => 'HAT-GREEN',
            'expectedTitles' => ['Green Hat'],
        ];

        yield 'matches by description' => [
            'term' => 'comfortable',
            'expectedTitles' => ['Red Shoes', 'Blue Shoes'],
        ];

        yield 'matches by ean' => [
            'term' => '1111111111111',
            'expectedTitles' => ['Red Shoes'],
        ];

        yield 'matches by subtitle' => [
            'term' => 'Autumn Collection',
            'expectedTitles' => ['Yellow Hat'],
        ];
    }

    #[Test]
    public function noMatchReturnsNoResults(): void
    {
        $subject = $this->get(ProductRepository::class);

        $this->assertSame([], $this->titles($subject->search('doesnotexist', null, 0, 10)));
        $this->assertSame(0, $subject->countSearchResults('doesnotexist', null));
    }

    #[Test]
    public function categoryFilterNarrowsResults(): void
    {
        $subject = $this->get(ProductRepository::class);

        $this->assertSame(['Red Shoes', 'Blue Shoes'], $this->titles($subject->search('Shoes', 10, 0, 10)));
        $this->assertSame([], $this->titles($subject->search('Shoes', 11, 0, 10)));
    }

    #[Test]
    public function limitAndOffsetPaginateResults(): void
    {
        $subject = $this->get(ProductRepository::class);

        $this->assertSame(2, $subject->countSearchResults('Shoes', null));
        $this->assertSame(['Red Shoes'], $this->titles($subject->search('Shoes', null, 0, 1)));
        $this->assertSame(['Blue Shoes'], $this->titles($subject->search('Shoes', null, 1, 1)));
    }

    #[Test]
    public function literalPercentSignInTheTermIsNotTreatedAsAWildcard(): void
    {
        $subject = $this->get(ProductRepository::class);

        $this->assertSame([], $this->titles($subject->search('%', null, 0, 10)));
    }

    /**
     * SQLite has no default LIKE escape character, so the escaping only holds where the query declares one.
     *
     * @param string[] $expectedTitles
     */
    #[Test]
    #[DataProvider('wildcardCharacterProvider')]
    public function wildcardCharactersInTheTermMatchLiterally(string $term, array $expectedTitles): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/search_wildcards.csv');
        $subject = $this->get(ProductRepository::class);

        $this->assertSame($expectedTitles, $this->titles($subject->search($term, null, 0, 10)));
        $this->assertSame(count($expectedTitles), $subject->countSearchResults($term, null));
    }

    public static function wildcardCharacterProvider(): \Generator
    {
        yield 'an underscore matches only an underscore' => [
            'term' => 'item_1',
            'expectedTitles' => ['Underscore Item'],
        ];
        yield 'a percent sign matches only a percent sign' => [
            'term' => '50%',
            'expectedTitles' => ['Percent Item'],
        ];
        yield 'a backslash matches only a backslash' => [
            'term' => 'path\\to',
            'expectedTitles' => ['Backslash Item'],
        ];
    }

    /**
     * @param iterable<Product> $products
     * @return string[]
     */
    private function titles(iterable $products): array
    {
        return array_map(static fn(Product $product): string => $product->getTitle(), iterator_to_array($products));
    }
}
