<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CategoryParentCycleHookTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryParentCycleHookTest/categories.csv');
    }

    #[Test]
    #[DataProvider('parentDataProvider')]
    public function aCategoryCannotBecomeItsOwnAncestor(int $categoryUid, int $newParentUid, int $expectedParentUid): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_products_domain_model_category' => [
                    $categoryUid => [
                        'parent_category' => $newParentUid,
                    ],
                ],
            ],
            []
        );

        $dataHandler->process_datamap();

        $row = $this->getAllRecords('tx_products_domain_model_category', true)[$categoryUid];
        $this->assertSame($expectedParentUid, (int)$row['parent_category']);
    }

    public static function parentDataProvider(): \Generator
    {
        yield 'its own parent is refused' => [
            'categoryUid' => 1,
            'newParentUid' => 1,
            'expectedParentUid' => 0,
        ];
        yield 'one of its descendants is refused' => [
            'categoryUid' => 1,
            'newParentUid' => 3,
            'expectedParentUid' => 0,
        ];
        yield 'an unrelated category is accepted' => [
            'categoryUid' => 1,
            'newParentUid' => 4,
            'expectedParentUid' => 4,
        ];
        yield 'moving a descendant further up is accepted' => [
            'categoryUid' => 3,
            'newParentUid' => 1,
            'expectedParentUid' => 1,
        ];
    }
}
