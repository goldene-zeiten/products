<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ProductRepositorySearchTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/search.csv');
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
     * @param iterable<Product> $products
     * @return string[]
     */
    private function titles(iterable $products): array
    {
        return array_map(static fn(Product $product): string => $product->getTitle(), iterator_to_array($products));
    }
}
