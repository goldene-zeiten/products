<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Backend;

use GoldeneZeiten\Products\Backend\CategoryPermissionGuard;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CategoryPermissionGuardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private CategoryPermissionGuard $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/category_permissions.csv');
        $this->subject = $this->get(CategoryPermissionGuard::class);
    }

    #[Test]
    public function adminIsAlwaysAllowed(): void
    {
        $backendUser = $this->setUpBackendUser(1);

        $this->assertTrue($this->subject->isCategoryEditable(100, $backendUser));
    }

    #[Test]
    public function ownerUserIsGrantedTheirOwnPermission(): void
    {
        $backendUser = $this->setUpBackendUser(2);

        $this->assertTrue($this->subject->isCategoryEditable(100, $backendUser));
    }

    #[Test]
    public function nonOwnerIsDeniedWithoutGroupOrEverybodyGrant(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        $this->assertFalse($this->subject->isCategoryEditable(100, $backendUser));
    }

    #[Test]
    public function everybodyGrantAllowsAnyUser(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        $this->assertTrue($this->subject->isCategoryEditable(101, $backendUser));
    }

    #[Test]
    public function groupGrantAllowsMemberUser(): void
    {
        $backendUser = $this->setUpBackendUser(4);

        $this->assertTrue($this->subject->isCategoryEditable(102, $backendUser));
    }

    #[Test]
    public function groupGrantDeniesNonMemberUser(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        $this->assertFalse($this->subject->isCategoryEditable(102, $backendUser));
    }

    #[Test]
    public function missingCategoryIsDenied(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        $this->assertFalse($this->subject->isCategoryEditable(999999, $backendUser));
    }

    #[Test]
    public function adminIsAlwaysAllowedToDelete(): void
    {
        $backendUser = $this->setUpBackendUser(1);

        $this->assertTrue($this->subject->isCategoryDeletable(100, $backendUser));
    }

    #[Test]
    public function ownerWithEditOnlyPermissionCannotDelete(): void
    {
        $backendUser = $this->setUpBackendUser(2);

        $this->assertFalse($this->subject->isCategoryDeletable(100, $backendUser));
    }

    #[Test]
    public function ownerWithEditAndDeletePermissionCanDelete(): void
    {
        $backendUser = $this->setUpBackendUser(2);

        $this->assertTrue($this->subject->isCategoryDeletable(103, $backendUser));
    }

    #[Test]
    public function missingCategoryIsDeniedForDelete(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        $this->assertFalse($this->subject->isCategoryDeletable(999999, $backendUser));
    }
}
