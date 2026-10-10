<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Core\Backend\CategoryPermissionGuard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Categories carry page-like permissions. A new category belongs to the user creating it, and only
 * an admin or the owner may change who may do what with it.
 */
final class CategoryOwnershipHookTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryOwnershipHookTest/categories_with_owners.csv');
    }

    #[Test]
    public function aNewCategoryBelongsToItsCreatorWhoMayEditIt(): void
    {
        $backendUser = $this->setUpBackendUser(3);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_products_domain_model_category' => [
                'NEW1' => [
                    'pid' => 2,
                    'sys_language_uid' => 0,
                    'title' => 'Created by user 3',
                    'parent_category' => 10,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $this->assertSame([], $dataHandler->errorLog);
        $newUid = (int)$dataHandler->substNEWwithIDs['NEW1'];
        $row = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_category')
            ->select(['perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody'], 'tx_products_domain_model_category', ['uid' => $newUid])
            ->fetchAssociative();
        $this->assertSame(
            [
                'perms_userid' => 3,
                'perms_groupid' => 1,
                'perms_user' => 15,
                'perms_group' => 11,
                'perms_everybody' => 0,
            ],
            array_map('intval', (array)$row)
        );
        $this->assertTrue($this->get(CategoryPermissionGuard::class)->isCategoryEditable($newUid, $backendUser));
    }

    #[Test]
    public function aUserWhoIsNotTheOwnerCannotWidenTheCategoryPermissions(): void
    {
        $backendUser = $this->setUpBackendUser(3);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_products_domain_model_category' => [
                20 => [
                    'title' => 'Renamed by user 3',
                    'perms_everybody' => 15,
                    'perms_userid' => 3,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $row = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_category')
            ->select(['title', 'perms_userid', 'perms_everybody'], 'tx_products_domain_model_category', ['uid' => 20])
            ->fetchAssociative();
        $this->assertSame('Renamed by user 3', $row['title'] ?? null);
        $this->assertSame(2, (int)($row['perms_userid'] ?? 0));
        $this->assertSame(0, (int)($row['perms_everybody'] ?? -1));
    }

    #[Test]
    public function theOwnerMayChangeThePermissionsButNotHandOverTheCategory(): void
    {
        $backendUser = $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_products_domain_model_category' => [
                20 => [
                    'perms_everybody' => 1,
                    'perms_userid' => 3,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $row = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_category')
            ->select(['perms_userid', 'perms_everybody'], 'tx_products_domain_model_category', ['uid' => 20])
            ->fetchAssociative();
        $this->assertSame(2, (int)($row['perms_userid'] ?? 0));
        $this->assertSame(1, (int)($row['perms_everybody'] ?? 0));
    }

    #[Test]
    public function anAdminMayHandOverTheCategory(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_products_domain_model_category' => [
                20 => [
                    'perms_userid' => 3,
                    'perms_everybody' => 1,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $row = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_category')
            ->select(['perms_userid', 'perms_everybody'], 'tx_products_domain_model_category', ['uid' => 20])
            ->fetchAssociative();
        $this->assertSame(3, (int)($row['perms_userid'] ?? 0));
        $this->assertSame(1, (int)($row['perms_everybody'] ?? 0));
    }
}
