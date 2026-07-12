<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Hooks\CategoryMountAccessHook;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Regression coverage for a real (fixed) bug: CategoryMountAccessHook::processCmdmap() enforced
 * category-mount visibility for delete/move commands but never consulted CategoryPermissionGuard -
 * a user denied "edit" via perms_user/perms_group, but within an accessible mount, could still
 * delete the category outright through the standard record-list/edit-form delete action.
 *
 * Exercises the hook method directly (rather than a full DataHandler::process_cmdmap() run) so the
 * test proves this extension's own access rule in isolation, without also having to reconstruct
 * TYPO3 core's independent tables_modify/non_exclude_fields ACL for a non-admin user.
 */
final class CategoryMountAccessHookDeleteTest extends AbstractFunctionalTestCase
{
    private const TABLE = 'tx_products_domain_model_category';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryMountAccessHookDeleteTest/category_delete_permission.csv');
    }

    #[Test]
    #[DataProvider('deleteCommandDataProvider')]
    public function processCmdmapEnforcesCategoryDeletePermission(int $categoryUid, bool $expectedCommandIsProcessed): void
    {
        $backendUser = $this->setUpBackendUser(10);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], []);
        $dataHandler->BE_USER = $backendUser;

        $commandIsProcessed = false;
        $this->get(CategoryMountAccessHook::class)->processCmdmap('delete', self::TABLE, $categoryUid, 1, $commandIsProcessed, $dataHandler, false);

        $this->assertSame($expectedCommandIsProcessed, $commandIsProcessed);
    }

    public static function deleteCommandDataProvider(): \Generator
    {
        yield 'delete is denied when user lacks category delete permission' => [
            'categoryUid' => 200,
            'expectedCommandIsProcessed' => true,
        ];
        yield 'delete is allowed when user has category delete permission' => [
            'categoryUid' => 201,
            'expectedCommandIsProcessed' => false,
        ];
    }
}
