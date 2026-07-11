<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Category;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CategoryTreeServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private CategoryTreeService $subject;
    private CategoryRepository $categoryRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/category_tree.csv');
        $this->categoryRepository = $this->get(CategoryRepository::class);
        $this->subject = new CategoryTreeService($this->categoryRepository);
    }

    #[Test]
    public function theTreeHasTwoTopLevelMainCategoriesAtDepthZero(): void
    {
        $tree = $this->subject->getTree();

        $this->assertCount(2, $tree);
        $this->assertSame('Main Category 1', $tree[0]->getCategory()->getTitle());
        $this->assertSame(0, $tree[0]->getDepth());
        $this->assertSame('Main Category 2', $tree[1]->getCategory()->getTitle());
    }

    #[Test]
    public function mainCategoryOneHasFiveSubCategoriesAtDepthOne(): void
    {
        $tree = $this->subject->getTree();

        $subCategories = $tree[0]->getChildren();
        $this->assertCount(5, $subCategories);
        foreach ($subCategories as $subCategory) {
            $this->assertSame(1, $subCategory->getDepth());
        }
    }

    #[Test]
    public function subCategoryFiveHasThreeLeafCategoriesAtDepthTwoWithNoChildrenOfTheirOwn(): void
    {
        $tree = $this->subject->getTree();

        $subCategoryFive = $tree[0]->getChildren()[4];
        $this->assertSame('Sub Category 5', $subCategoryFive->getCategory()->getTitle());
        $leaves = $subCategoryFive->getChildren();
        $this->assertCount(3, $leaves);
        foreach ($leaves as $leaf) {
            $this->assertSame(2, $leaf->getDepth());
            $this->assertSame([], $leaf->getChildren());
        }
    }

    #[Test]
    public function ancestorChainIsRootFirstIncludingTheCategoryItself(): void
    {
        $lastCategoryThree = $this->findCategory(22);

        $chain = $this->subject->getAncestorChain($lastCategoryThree);

        $this->assertSame(
            ['Main Category 1', 'Sub Category 5', 'Last Category 3'],
            array_map(static fn(Category $category): string => $category->getTitle(), $chain)
        );
    }

    #[Test]
    public function resolveSlugPathBuildsTheFullNestedPathForACategoryAndProduct(): void
    {
        $lastCategoryThree = $this->findCategory(22);
        $productTwo = $this->findProduct(102);

        $path = $this->subject->resolveSlugPath($lastCategoryThree, $productTwo);

        $this->assertSame('main-category-1/sub-category-5/last-category-3/product-2', $path);
    }

    #[Test]
    public function resolveSlugPathWithoutAProductReturnsOnlyTheCategoryAncestorPath(): void
    {
        $lastCategoryThree = $this->findCategory(22);

        $path = $this->subject->resolveSlugPath($lastCategoryThree);

        $this->assertSame('main-category-1/sub-category-5/last-category-3', $path);
    }

    #[Test]
    public function siblingLeavesSharingTheSameOwnSlugSegmentResolveToDistinctFullPaths(): void
    {
        $leafUnderMainOne = $this->findCategory(22);
        $leafUnderMainTwo = $this->findCategory(23);
        $this->assertSame($leafUnderMainOne->getTitle(), $leafUnderMainTwo->getTitle());

        $pathOne = $this->subject->resolveSlugPath($leafUnderMainOne);
        $pathTwo = $this->subject->resolveSlugPath($leafUnderMainTwo);

        $this->assertNotSame($pathOne, $pathTwo);
        $this->assertSame('main-category-1/sub-category-5/last-category-3', $pathOne);
        $this->assertSame('main-category-2/sub-category-6/last-category-3', $pathTwo);
    }

    private function findCategory(int $uid): Category
    {
        $category = $this->categoryRepository->findByUid($uid);
        $this->assertInstanceOf(Category::class, $category);
        return $category;
    }

    private function findProduct(int $uid): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }
}
