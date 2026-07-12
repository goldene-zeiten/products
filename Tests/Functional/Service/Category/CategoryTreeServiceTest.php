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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryTreeServiceTest/category_tree.csv');
    }

    #[Test]
    public function theTreeHasTwoTopLevelMainCategoriesAtDepthZero(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $tree = $subject->getTree();

        $this->assertCount(2, $tree);
        $this->assertSame('Main Category 1', $tree[0]->getCategory()->getTitle());
        $this->assertSame(0, $tree[0]->getDepth());
        $this->assertSame('Main Category 2', $tree[1]->getCategory()->getTitle());
    }

    #[Test]
    public function mainCategoryOneHasFiveSubCategoriesAtDepthOne(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $tree = $subject->getTree();

        $subCategories = $tree[0]->getChildren();
        $this->assertCount(5, $subCategories);
        foreach ($subCategories as $subCategory) {
            $this->assertSame(1, $subCategory->getDepth());
        }
    }

    #[Test]
    public function subCategoryFiveHasThreeLeafCategoriesAtDepthTwoWithNoChildrenOfTheirOwn(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $tree = $subject->getTree();

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
    public function subtreeIsRootedAtTheEntryPointCategoryItself(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $subtree = $subject->getSubtree(6);

        $this->assertCount(1, $subtree);
        $this->assertSame('Sub Category 5', $subtree[0]->getCategory()->getTitle());
        $this->assertSame(0, $subtree[0]->getDepth());
    }

    #[Test]
    public function subtreeDefaultLevelsIncludesOnlyOneLevelOfChildrenBeneathTheEntryPoint(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $subtree = $subject->getSubtree(6);

        $children = $subtree[0]->getChildren();
        $this->assertCount(3, $children);
        foreach ($children as $child) {
            $this->assertSame(1, $child->getDepth());
            $this->assertSame([], $child->getChildren());
        }
    }

    #[Test]
    public function subtreeLevelsControlsHowManyLevelsBeneathTheEntryPointAreIncluded(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $subtree = $subject->getSubtree(1, 2);

        $subCategoryFive = $subtree[0]->getChildren()[4];
        $this->assertSame('Sub Category 5', $subCategoryFive->getCategory()->getTitle());
        $this->assertCount(3, $subCategoryFive->getChildren());
    }

    #[Test]
    public function subtreeForANonExistentEntryPointCategoryReturnsAnEmptyArray(): void
    {
        $subject = $this->get(CategoryTreeService::class);

        $subtree = $subject->getSubtree(999999);

        $this->assertSame([], $subtree);
    }

    #[Test]
    public function ancestorChainIsRootFirstIncludingTheCategoryItself(): void
    {
        $subject = $this->get(CategoryTreeService::class);
        $lastCategoryThree = $this->findCategory(22);

        $chain = $subject->getAncestorChain($lastCategoryThree);

        $this->assertSame(
            ['Main Category 1', 'Sub Category 5', 'Last Category 3'],
            array_map(static fn(Category $category): string => $category->getTitle(), $chain)
        );
    }

    #[Test]
    public function resolveSlugPathBuildsTheFullNestedPathForACategoryAndProduct(): void
    {
        $subject = $this->get(CategoryTreeService::class);
        $lastCategoryThree = $this->findCategory(22);
        $productTwo = $this->findProduct(102);

        $path = $subject->resolveSlugPath($lastCategoryThree, $productTwo);

        $this->assertSame('main-category-1/sub-category-5/last-category-3/product-2', $path);
    }

    #[Test]
    public function resolveSlugPathWithoutAProductReturnsOnlyTheCategoryAncestorPath(): void
    {
        $subject = $this->get(CategoryTreeService::class);
        $lastCategoryThree = $this->findCategory(22);

        $path = $subject->resolveSlugPath($lastCategoryThree);

        $this->assertSame('main-category-1/sub-category-5/last-category-3', $path);
    }

    #[Test]
    public function siblingLeavesSharingTheSameOwnSlugSegmentResolveToDistinctFullPaths(): void
    {
        $subject = $this->get(CategoryTreeService::class);
        $leafUnderMainOne = $this->findCategory(22);
        $leafUnderMainTwo = $this->findCategory(23);
        $this->assertSame($leafUnderMainOne->getTitle(), $leafUnderMainTwo->getTitle());

        $pathOne = $subject->resolveSlugPath($leafUnderMainOne);
        $pathTwo = $subject->resolveSlugPath($leafUnderMainTwo);

        $this->assertNotSame($pathOne, $pathTwo);
        $this->assertSame('main-category-1/sub-category-5/last-category-3', $pathOne);
        $this->assertSame('main-category-2/sub-category-6/last-category-3', $pathTwo);
    }

    private function findCategory(int $uid): Category
    {
        $category = $this->get(CategoryRepository::class)->findByUid($uid);
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
