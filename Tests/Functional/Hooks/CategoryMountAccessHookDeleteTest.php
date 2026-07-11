<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Hooks\CategoryMountAccessHook;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
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

    private CategoryMountAccessHook $hook;
    private DataHandler $dataHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/category_delete_permission.csv');
        $backendUser = $this->setUpBackendUser(10);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $this->hook = $this->get(CategoryMountAccessHook::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->dataHandler->start([], []);
        $this->dataHandler->BE_USER = $backendUser;
    }

    #[Test]
    public function deleteIsDeniedWhenUserLacksCategoryDeletePermission(): void
    {
        self::assertTrue($this->processDeleteCommand(200));
    }

    #[Test]
    public function deleteIsAllowedWhenUserHasCategoryDeletePermission(): void
    {
        self::assertFalse($this->processDeleteCommand(201));
    }

    /**
     * @return bool whether the hook marked the command as processed (i.e. denied it)
     */
    private function processDeleteCommand(int $categoryUid): bool
    {
        $commandIsProcessed = false;
        $this->hook->processCmdmap('delete', self::TABLE, $categoryUid, 1, $commandIsProcessed, $this->dataHandler, false);
        return $commandIsProcessed;
    }
}
