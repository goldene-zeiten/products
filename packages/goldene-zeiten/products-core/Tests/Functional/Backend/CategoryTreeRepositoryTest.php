<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\CategoryAccessGuard;
use GoldeneZeiten\Products\Core\Backend\CategoryMountResolver;
use GoldeneZeiten\Products\Core\Backend\CategoryTreeRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CategoryTreeRepositoryTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryTreeRepositoryTest/category_tree_backend.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function fetchRootCategoriesReturnsOnlyTopLevelCategories(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $uids = array_column($treeRepository->fetchRootCategories(), 'uid');

        $this->assertSame([10, 13], $uids);
    }

    #[Test]
    public function fetchChildCategoriesIncludesHiddenButExcludesDeleted(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $uids = array_column($treeRepository->fetchChildCategories(10), 'uid');

        $this->assertSame([11, 12], $uids);
    }

    #[Test]
    public function categoryHasChildrenIsFalseForALeafCategory(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $this->assertFalse($treeRepository->categoryHasChildren(11));
        $this->assertTrue($treeRepository->categoryHasChildren(10));
    }

    #[Test]
    public function fetchProductsByCategoryReturnsProductsLinkedViaMm(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $uids = array_column($treeRepository->fetchProductsByCategory(11), 'uid');

        $this->assertSame([20], $uids);
        $this->assertSame([], array_column($treeRepository->fetchProductsByCategory(10), 'uid'));
    }

    #[Test]
    public function fetchArticlesByProductReturnsArticlesOfThatProduct(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $uids = array_column($treeRepository->fetchArticlesByProduct(20), 'uid');

        $this->assertSame([30], $uids);
        $this->assertTrue($treeRepository->productHasArticles(20));
    }

    #[Test]
    public function fetchCategoryUidsOfProductReturnsLinkedCategories(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $this->assertSame([11], $treeRepository->fetchCategoryUidsOfProduct(20));
    }

    #[Test]
    public function fetchParentCategoryUidWalksTheParentChain(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $this->assertSame(10, $treeRepository->fetchParentCategoryUid(11));
        $this->assertSame(0, $treeRepository->fetchParentCategoryUid(10));
    }

    #[Test]
    public function categoryExistsIsFalseForDeletedOrMissingRecords(): void
    {
        $treeRepository = $this->get(CategoryTreeRepository::class);

        $this->assertTrue($treeRepository->categoryExists(10));
        $this->assertFalse($treeRepository->categoryExists(14));
        $this->assertFalse($treeRepository->categoryExists(999999));
    }

    #[Test]
    public function adminUserIsUnrestricted(): void
    {
        $mountResolver = $this->get(CategoryMountResolver::class);
        $accessGuard = $this->get(CategoryAccessGuard::class);
        $backendUser = $this->setUpBackendUser(1);

        $this->assertNull($mountResolver->resolveMountUids($backendUser));
        $this->assertTrue($accessGuard->isCategoryAccessible(13, null));
    }

    #[Test]
    public function ownMountRestrictsAccessToItsSubtree(): void
    {
        $mountResolver = $this->get(CategoryMountResolver::class);
        $accessGuard = $this->get(CategoryAccessGuard::class);
        $backendUser = $this->setUpBackendUser(2);
        $mounts = $mountResolver->resolveMountUids($backendUser);

        $this->assertSame([10], $mounts);
        $this->assertTrue($accessGuard->isCategoryAccessible(10, $mounts));
        $this->assertTrue($accessGuard->isCategoryAccessible(11, $mounts));
        $this->assertFalse($accessGuard->isCategoryAccessible(13, $mounts));
        $this->assertTrue($accessGuard->isProductAccessible(20, $mounts));
    }

    #[Test]
    public function groupMountIsMergedIntoEffectiveMounts(): void
    {
        $mountResolver = $this->get(CategoryMountResolver::class);
        $accessGuard = $this->get(CategoryAccessGuard::class);
        $backendUser = $this->setUpBackendUser(3);
        $mounts = $mountResolver->resolveMountUids($backendUser);

        $this->assertSame([13], $mounts);
        $this->assertTrue($accessGuard->isCategoryAccessible(13, $mounts));
        $this->assertFalse($accessGuard->isCategoryAccessible(10, $mounts));
    }

    #[Test]
    public function userWithoutAnyMountSeesNothing(): void
    {
        $mountResolver = $this->get(CategoryMountResolver::class);
        $accessGuard = $this->get(CategoryAccessGuard::class);
        $backendUser = $this->setUpBackendUser(4);
        $mounts = $mountResolver->resolveMountUids($backendUser);

        $this->assertSame([], $mounts);
        $this->assertFalse($accessGuard->isCategoryAccessible(10, $mounts));
        $this->assertFalse($accessGuard->isProductAccessible(20, $mounts));
    }
}
