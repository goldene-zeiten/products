<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Hooks;

use GoldeneZeiten\Products\Core\Page\PageTypes;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Puts the withdrawal form onto every page an editor creates with (or switches to) the withdrawal
 * doktype, by inserting the Withdrawal plugin as its content. The page type must render the form
 * with no further editor steps regardless of how a site renders page content - a content element in
 * the main column is the one mechanism every site template renders.
 *
 * @internal Registered as a classic DataHandler hook in ext_localconf.php.
 *
 * Public, because TYPO3 instantiates a hook through makeInstance, which only injects dependencies
 * into a public service.
 */
#[Autoconfigure(public: true)]
final class WithdrawalPageContentHook
{
    private const WITHDRAWAL_CTYPE = 'productscore_withdrawal';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(string $status, string $table, int|string $id, array $fieldArray, DataHandler $dataHandler): void
    {
        if ($table !== 'pages' || (int)($fieldArray['doktype'] ?? 0) !== PageTypes::DOKTYPE_WITHDRAWAL) {
            return;
        }
        $pageUid = is_int($id) ? $id : (int)($dataHandler->substNEWwithIDs[$id] ?? 0);
        if ($pageUid === 0 || $this->pageHasWithdrawalPlugin($pageUid)) {
            return;
        }
        $this->insertWithdrawalPlugin($pageUid, $dataHandler);
    }

    /**
     * Hidden elements count as present - an editor who deliberately disabled the plugin should
     * not have it silently re-created on the next page save.
     */
    private function pageHasWithdrawalPlugin(int $pageUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $count = (int)$queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter(self::WITHDRAWAL_CTYPE, Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchOne();
        return $count > 0;
    }

    private function insertWithdrawalPlugin(int $pageUid, DataHandler $parent): void
    {
        $data = [
            'tt_content' => [
                'NEWproductswithdrawal' => [
                    'pid' => $pageUid,
                    'CType' => self::WITHDRAWAL_CTYPE,
                    'colPos' => 0,
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, [], $parent->BE_USER);
        $dataHandler->process_datamap();
    }
}
