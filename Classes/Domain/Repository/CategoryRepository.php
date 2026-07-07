<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Category;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @extends AbstractReadOnlyRepository<Category>
 */
final class CategoryRepository extends AbstractReadOnlyRepository
{
    /**
     * Without an explicit ORDER BY, row order is undefined and differs across DBMS/versions
     * (observed to differ between PostgreSQL versions). "sorting" is the TCA-editable backend
     * order; "uid" breaks ties deterministically for rows sharing the same sorting value.
     *
     * @var array<non-empty-string, QueryInterface::ORDER_*>
     */
    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING,
    ];

    /**
     * Callers such as CategoryTreeService may run outside a request context where the plugin's
     * configured `persistence.storagePid` applies (e.g. route enhancer resolution) - the whole
     * category tree must be visible regardless of calling context.
     *
     * @return Category[]
     */
    public function findAllIgnoringStoragePage(): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query->execute()->toArray();
    }
}
