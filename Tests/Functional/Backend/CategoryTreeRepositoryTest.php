<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Backend;

use GoldeneZeiten\Products\Backend\CategoryAccessGuard;
use GoldeneZeiten\Products\Backend\CategoryMountResolver;
use GoldeneZeiten\Products\Backend\CategoryTreeRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CategoryTreeRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private CategoryTreeRepository $treeRepository;
    private CategoryMountResolver $mountResolver;
    private CategoryAccessGuard $accessGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->treeRepository = $this->get(CategoryTreeRepository::class);
        $this->mountResolver = $this->get(CategoryMountResolver::class);
        $this->accessGuard = $this->get(CategoryAccessGuard::class);
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/category_tree_backend.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function fetchRootCategoriesReturnsOnlyTopLevelCategories(): void
    {
        $uids = array_column($this->treeRepository->fetchRootCategories(), 'uid');

        self::assertSame([10, 13], $uids);
    }

    #[Test]
    public function fetchChildCategoriesIncludesHiddenButExcludesDeleted(): void
    {
        $uids = array_column($this->treeRepository->fetchChildCategories(10), 'uid');

        self::assertSame([11, 12], $uids);
    }

    #[Test]
    public function categoryHasChildrenIsFalseForALeafCategory(): void
    {
        self::assertFalse($this->treeRepository->categoryHasChildren(11));
        self::assertTrue($this->treeRepository->categoryHasChildren(10));
    }

    #[Test]
    public function fetchProductsByCategoryReturnsProductsLinkedViaMm(): void
    {
        $uids = array_column($this->treeRepository->fetchProductsByCategory(11), 'uid');

        self::assertSame([20], $uids);
        self::assertSame([], array_column($this->treeRepository->fetchProductsByCategory(10), 'uid'));
    }

    #[Test]
    public function fetchArticlesByProductReturnsArticlesOfThatProduct(): void
    {
        $uids = array_column($this->treeRepository->fetchArticlesByProduct(20), 'uid');

        self::assertSame([30], $uids);
        self::assertTrue($this->treeRepository->productHasArticles(20));
    }

    #[Test]
    public function fetchCategoryUidsOfProductReturnsLinkedCategories(): void
    {
        self::assertSame([11], $this->treeRepository->fetchCategoryUidsOfProduct(20));
    }

    #[Test]
    public function fetchParentCategoryUidWalksTheParentChain(): void
    {
        self::assertSame(10, $this->treeRepository->fetchParentCategoryUid(11));
        self::assertSame(0, $this->treeRepository->fetchParentCategoryUid(10));
    }

    #[Test]
    public function categoryExistsIsFalseForDeletedOrMissingRecords(): void
    {
        self::assertTrue($this->treeRepository->categoryExists(10));
        self::assertFalse($this->treeRepository->categoryExists(14));
        self::assertFalse($this->treeRepository->categoryExists(999999));
    }

    #[Test]
    public function adminUserIsUnrestricted(): void
    {
        $backendUser = $this->setUpBackendUser(1);

        self::assertNull($this->mountResolver->resolveMountUids($backendUser));
        self::assertTrue($this->accessGuard->isCategoryAccessible(13, null));
    }

    #[Test]
    public function ownMountRestrictsAccessToItsSubtree(): void
    {
        $backendUser = $this->setUpBackendUser(2);
        $mounts = $this->mountResolver->resolveMountUids($backendUser);

        self::assertSame([10], $mounts);
        self::assertTrue($this->accessGuard->isCategoryAccessible(10, $mounts));
        self::assertTrue($this->accessGuard->isCategoryAccessible(11, $mounts));
        self::assertFalse($this->accessGuard->isCategoryAccessible(13, $mounts));
        self::assertTrue($this->accessGuard->isProductAccessible(20, $mounts));
    }

    #[Test]
    public function groupMountIsMergedIntoEffectiveMounts(): void
    {
        $backendUser = $this->setUpBackendUser(3);
        $mounts = $this->mountResolver->resolveMountUids($backendUser);

        self::assertSame([13], $mounts);
        self::assertTrue($this->accessGuard->isCategoryAccessible(13, $mounts));
        self::assertFalse($this->accessGuard->isCategoryAccessible(10, $mounts));
    }

    #[Test]
    public function userWithoutAnyMountSeesNothing(): void
    {
        $backendUser = $this->setUpBackendUser(4);
        $mounts = $this->mountResolver->resolveMountUids($backendUser);

        self::assertSame([], $mounts);
        self::assertFalse($this->accessGuard->isCategoryAccessible(10, $mounts));
        self::assertFalse($this->accessGuard->isProductAccessible(20, $mounts));
    }
}
