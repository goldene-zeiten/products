<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\CategoryPermissionGuard;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class CategoryPermissionGuardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryPermissionGuardTest/category_permissions.csv');
    }

    #[Test]
    #[DataProvider('isCategoryEditableProvider')]
    public function isCategoryEditable(int $backendUserUid, int $categoryUid, bool $expected): void
    {
        $subject = $this->get(CategoryPermissionGuard::class);
        $backendUser = $this->setUpBackendUser($backendUserUid);

        $this->assertSame($expected, $subject->isCategoryEditable($categoryUid, $backendUser));
    }

    public static function isCategoryEditableProvider(): \Generator
    {
        yield 'admin is always allowed' => [
            'backendUserUid' => 1,
            'categoryUid' => 100,
            'expected' => true,
        ];

        yield 'owner user is granted their own permission' => [
            'backendUserUid' => 2,
            'categoryUid' => 100,
            'expected' => true,
        ];

        yield 'non-owner is denied without group or everybody grant' => [
            'backendUserUid' => 3,
            'categoryUid' => 100,
            'expected' => false,
        ];

        yield 'everybody grant allows any user' => [
            'backendUserUid' => 3,
            'categoryUid' => 101,
            'expected' => true,
        ];

        yield 'group grant allows member user' => [
            'backendUserUid' => 4,
            'categoryUid' => 102,
            'expected' => true,
        ];

        yield 'group grant denies non-member user' => [
            'backendUserUid' => 3,
            'categoryUid' => 102,
            'expected' => false,
        ];

        yield 'missing category is denied' => [
            'backendUserUid' => 3,
            'categoryUid' => 999999,
            'expected' => false,
        ];
    }

    #[Test]
    #[DataProvider('isCategoryDeletableProvider')]
    public function isCategoryDeletable(int $backendUserUid, int $categoryUid, bool $expected): void
    {
        $subject = $this->get(CategoryPermissionGuard::class);
        $backendUser = $this->setUpBackendUser($backendUserUid);

        $this->assertSame($expected, $subject->isCategoryDeletable($categoryUid, $backendUser));
    }

    public static function isCategoryDeletableProvider(): \Generator
    {
        yield 'admin is always allowed to delete' => [
            'backendUserUid' => 1,
            'categoryUid' => 100,
            'expected' => true,
        ];

        yield 'owner with edit-only permission cannot delete' => [
            'backendUserUid' => 2,
            'categoryUid' => 100,
            'expected' => false,
        ];

        yield 'owner with edit and delete permission can delete' => [
            'backendUserUid' => 2,
            'categoryUid' => 103,
            'expected' => true,
        ];

        yield 'missing category is denied for delete' => [
            'backendUserUid' => 3,
            'categoryUid' => 999999,
            'expected' => false,
        ];
    }
}
