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

        self::assertTrue($this->subject->isCategoryEditable(100, $backendUser));
    }

    #[Test]
    public function ownerUserIsGrantedTheirOwnPermission(): void
    {
        $backendUser = $this->setUpBackendUser(2);

        self::assertTrue($this->subject->isCategoryEditable(100, $backendUser));
    }

    #[Test]
    public function nonOwnerIsDeniedWithoutGroupOrEverybodyGrant(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        self::assertFalse($this->subject->isCategoryEditable(100, $backendUser));
    }

    #[Test]
    public function everybodyGrantAllowsAnyUser(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        self::assertTrue($this->subject->isCategoryEditable(101, $backendUser));
    }

    #[Test]
    public function groupGrantAllowsMemberUser(): void
    {
        $backendUser = $this->setUpBackendUser(4);

        self::assertTrue($this->subject->isCategoryEditable(102, $backendUser));
    }

    #[Test]
    public function groupGrantDeniesNonMemberUser(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        self::assertFalse($this->subject->isCategoryEditable(102, $backendUser));
    }

    #[Test]
    public function missingCategoryIsDenied(): void
    {
        $backendUser = $this->setUpBackendUser(3);

        self::assertFalse($this->subject->isCategoryEditable(999999, $backendUser));
    }
}
