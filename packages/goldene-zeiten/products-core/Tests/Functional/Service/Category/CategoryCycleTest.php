<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Category;

use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Core\Pricing\CategoryDiscountResolver;
use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Category data saved before the parent-cycle guard existed - or imported - may still point a category
 * at itself or at one of its descendants. Walking such a tree must terminate.
 */
final class CategoryCycleTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryCycleTest/category_cycles.csv');
    }

    /**
     * @param int[] $expectedUids
     */
    #[Test]
    #[DataProvider('cycleDataProvider')]
    public function ancestorChainVisitsEveryCategoryOnce(int $categoryUid, array $expectedUids): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $chain = $subject->getAncestorChain($this->category($categoryUid));

        $this->assertSame($expectedUids, array_map(static fn(Category $category): ?int => $category->getUid(), $chain));
    }

    public static function cycleDataProvider(): \Generator
    {
        yield 'a category that is its own parent' => [
            'categoryUid' => 10,
            'expectedUids' => [10],
        ];
        yield 'two categories that are each others parent' => [
            'categoryUid' => 20,
            'expectedUids' => [21, 20],
        ];
    }

    #[Test]
    public function subtreeOfACycleStopsWhereItWouldRepeat(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $this->assertSame([20, 21], $subject->getSubtreeUids(20));
    }

    #[Test]
    #[DataProvider('discountModeDataProvider')]
    public function categoryDiscountOfACycleIsTheBestDiscountInIt(string $mode, float $expectedPercent): void
    {
        $subject = $this->get(CategoryDiscountResolver::class);
        $product = new Product();
        /** @var ObjectStorage<Category> $categories */
        $categories = new ObjectStorage();
        $categories->attach($this->category(20));
        $product->setCategories($categories);

        $this->assertSame($expectedPercent, $subject->getDiscountPercent($product, $mode));
    }

    public static function discountModeDataProvider(): \Generator
    {
        yield 'maxAcrossTree' => [
            'mode' => 'maxAcrossTree',
            'expectedPercent' => 20.0,
        ];
        yield 'nearestCategory' => [
            'mode' => 'nearestCategory',
            'expectedPercent' => 10.0,
        ];
    }

    private function category(int $uid): Category
    {
        $category = $this->get(CategoryRepository::class)->findByUidIgnoringStoragePage($uid);
        $this->assertInstanceOf(Category::class, $category);
        return $category;
    }
}
