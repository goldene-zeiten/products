<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\CategoryTreeReorderService;
use GoldeneZeiten\Products\Core\Backend\CategoryTreeRepository;
use GoldeneZeiten\Products\Core\Backend\Exception\CategoryTreeReorderFailedException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Drag and drop in the category tree reorders through DataHandler, so table permissions apply and the
 * change is logged like any other edit.
 */
final class CategoryTreeReorderServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryTreeReorderServiceTest/category_siblings.csv');
    }

    #[Test]
    public function aCategoryIsMovedBeforeTheGivenSibling(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $subject = $this->get(CategoryTreeReorderService::class);

        $subject->reorderCategory(13, 11, $backendUser);

        $this->assertSame([13, 11, 12], $this->childUidsOf(10));
    }

    #[Test]
    public function aCategoryIsMovedToTheEndWithoutASiblingToPrecede(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $subject = $this->get(CategoryTreeReorderService::class);

        $subject->reorderCategory(11, null, $backendUser);

        $this->assertSame([12, 13, 11], $this->childUidsOf(10));
    }

    #[Test]
    public function theMoveIsRecordedInTheHistory(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $subject = $this->get(CategoryTreeReorderService::class);

        $subject->reorderCategory(13, 12, $backendUser);

        $logCount = $this->getConnectionPool()->getConnectionForTable('sys_log')->count('uid', 'sys_log', [
            'tablename' => 'tx_products_domain_model_category',
            'recuid' => 13,
        ]);
        $this->assertGreaterThan(0, $logCount);
    }

    #[Test]
    public function aUserWithoutWriteAccessToTheTableCannotReorder(): void
    {
        $backendUser = $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $subject = $this->get(CategoryTreeReorderService::class);

        try {
            $subject->reorderCategory(13, 11, $backendUser);
            $this->fail('A user without write access must not reorder categories.');
        } catch (CategoryTreeReorderFailedException $exception) {
            $this->assertSame(1791663460, $exception->getCode());
        }

        $this->assertSame([11, 12, 13], $this->childUidsOf(10));
    }

    /**
     * @return int[]
     */
    private function childUidsOf(int $parentUid): array
    {
        return array_column($this->get(CategoryTreeRepository::class)->fetchChildCategories($parentUid), 'uid');
    }
}
