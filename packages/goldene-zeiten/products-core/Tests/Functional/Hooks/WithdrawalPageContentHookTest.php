<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Core\Page\PageTypes;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A page created with (or switched to) the withdrawal doktype must end up rendering the withdrawal
 * form without further editor steps - the hook inserts the Withdrawal plugin as page content.
 */
final class WithdrawalPageContentHookTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/WithdrawalPageContentHookTest/be_users.csv');
    }

    #[Test]
    public function creatingAWithdrawalPageInsertsTheWithdrawalPlugin(): void
    {
        $backendUser = $this->prepareBackendEnvironment();

        $pageUid = $this->createWithdrawalPage($backendUser);

        $contentRows = $this->withdrawalPluginRowsOn($pageUid);
        $this->assertCount(1, $contentRows);
    }

    #[Test]
    public function resavingTheWithdrawalPageDoesNotDuplicateThePlugin(): void
    {
        $backendUser = $this->prepareBackendEnvironment();
        $pageUid = $this->createWithdrawalPage($backendUser);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'pages' => [
                $pageUid => [
                    'title' => 'Withdraw from contract',
                    'doktype' => PageTypes::DOKTYPE_WITHDRAWAL,
                ],
            ],
        ], [], $backendUser);
        $dataHandler->process_datamap();

        $this->assertCount(1, $this->withdrawalPluginRowsOn($pageUid));
    }

    #[Test]
    public function creatingAnOrdinaryPageInsertsNothing(): void
    {
        $backendUser = $this->prepareBackendEnvironment();

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'pages' => [
                'NEW1' => [
                    'pid' => 0,
                    'title' => 'Ordinary page',
                    'doktype' => 1,
                ],
            ],
        ], [], $backendUser);
        $dataHandler->process_datamap();

        $this->assertCount(0, $this->getAllRecords('tt_content'));
    }

    private function prepareBackendEnvironment(): BackendUserAuthentication
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        return $backendUser;
    }

    private function createWithdrawalPage(BackendUserAuthentication $backendUser): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'pages' => [
                'NEW1' => [
                    'pid' => 0,
                    'title' => 'Withdrawal',
                    'doktype' => PageTypes::DOKTYPE_WITHDRAWAL,
                ],
            ],
        ], [], $backendUser);
        $dataHandler->process_datamap();

        $pageUid = (int)($dataHandler->substNEWwithIDs['NEW1'] ?? 0);
        $this->assertGreaterThan(0, $pageUid);
        return $pageUid;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function withdrawalPluginRowsOn(int $pageUid): array
    {
        return array_values(array_filter(
            $this->getAllRecords('tt_content'),
            static fn(array $row): bool => (int)$row['pid'] === $pageUid && $row['CType'] === 'productscore_withdrawal'
        ));
    }
}
