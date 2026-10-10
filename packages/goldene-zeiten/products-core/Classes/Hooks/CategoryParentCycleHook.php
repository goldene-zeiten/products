<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;

/**
 * Refuses a parent category that is the category itself or one of its descendants. Such a parent would
 * cut the category off the tree and make every walk up its ancestors loop.
 *
 * @internal Registered as a classic DataHandler hook in ext_localconf.php.
 *
 * Public, because TYPO3 instantiates a hook through makeInstance, which only injects dependencies
 * into a public service.
 */
#[Autoconfigure(public: true)]
final class CategoryParentCycleHook
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, int|string $id, DataHandler $dataHandler): void
    {
        if ($table !== 'tx_products_domain_model_category' || !is_numeric($id) || !array_key_exists('parent_category', $incomingFieldArray)) {
            return;
        }
        $categoryUid = (int)$id;
        $parentUid = $this->toUid($incomingFieldArray['parent_category']);
        if ($parentUid === 0 || !$this->isAncestorOrSelf($categoryUid, $parentUid)) {
            return;
        }
        unset($incomingFieldArray['parent_category']);
        $dataHandler->log(
            $table,
            $categoryUid,
            SystemLogDatabaseAction::UPDATE,
            null,
            SystemLogErrorClassification::USER_ERROR,
            'Category {uid} cannot be moved below category {parent}, which is the category itself or one of its descendants',
            null,
            ['uid' => $categoryUid, 'parent' => $parentUid]
        );
    }

    /**
     * Walks up from the proposed parent; meeting the category on the way means it would become its own
     * ancestor. A cycle already stored ends the walk at the first category met twice.
     */
    private function isAncestorOrSelf(int $categoryUid, int $parentUid): bool
    {
        $visited = [];
        $current = $parentUid;
        while ($current !== 0 && !isset($visited[$current])) {
            if ($current === $categoryUid) {
                return true;
            }
            $visited[$current] = true;
            $current = $this->parentOf($current);
        }
        return false;
    }

    private function parentOf(int $categoryUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_category');
        $queryBuilder->getRestrictions()->removeAll();
        $parent = $queryBuilder
            ->select('parent_category')
            ->from('tx_products_domain_model_category')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($categoryUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        return $parent === false ? 0 : (int)$parent;
    }

    /**
     * The select field submits a plain uid, or a table-prefixed one when it comes from a relation browser.
     */
    private function toUid(mixed $value): int
    {
        return preg_match('/(\d+)$/', (string)(is_scalar($value) ? $value : ''), $matches) === 1 ? (int)$matches[1] : 0;
    }
}
