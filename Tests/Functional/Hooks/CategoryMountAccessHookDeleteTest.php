<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Core\Hooks\CategoryMountAccessHook;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Regression: the hook must consult CategoryPermissionGuard for delete, not just visibility.
 */
final class CategoryMountAccessHookDeleteTest extends AbstractFunctionalTestCase
{
    private const TABLE = 'tx_products_domain_model_category';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
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
