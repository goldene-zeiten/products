<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProductRepositorySearchTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private ProductRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/search.csv');
        $this->subject = $this->get(ProductRepository::class);
    }

    #[Test]
    public function matchesByTitle(): void
    {
        self::assertSame(['Red Shoes', 'Blue Shoes'], $this->titles($this->subject->search('Shoes', null, 0, 10)));
    }

    #[Test]
    public function matchesByItemNumber(): void
    {
        self::assertSame(['Green Hat'], $this->titles($this->subject->search('HAT-GREEN', null, 0, 10)));
    }

    #[Test]
    public function matchesByDescription(): void
    {
        self::assertSame(['Red Shoes', 'Blue Shoes'], $this->titles($this->subject->search('comfortable', null, 0, 10)));
    }

    #[Test]
    public function matchesByEan(): void
    {
        self::assertSame(['Red Shoes'], $this->titles($this->subject->search('1111111111111', null, 0, 10)));
    }

    #[Test]
    public function noMatchReturnsNoResults(): void
    {
        self::assertSame([], $this->titles($this->subject->search('doesnotexist', null, 0, 10)));
        self::assertSame(0, $this->subject->countSearchResults('doesnotexist', null));
    }

    #[Test]
    public function categoryFilterNarrowsResults(): void
    {
        self::assertSame(['Red Shoes', 'Blue Shoes'], $this->titles($this->subject->search('Shoes', 10, 0, 10)));
        self::assertSame([], $this->titles($this->subject->search('Shoes', 11, 0, 10)));
    }

    #[Test]
    public function limitAndOffsetPaginateResults(): void
    {
        self::assertSame(2, $this->subject->countSearchResults('Shoes', null));
        self::assertSame(['Red Shoes'], $this->titles($this->subject->search('Shoes', null, 0, 1)));
        self::assertSame(['Blue Shoes'], $this->titles($this->subject->search('Shoes', null, 1, 1)));
    }

    #[Test]
    public function literalPercentSignInTheTermIsNotTreatedAsAWildcard(): void
    {
        self::assertSame([], $this->titles($this->subject->search('%', null, 0, 10)));
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
