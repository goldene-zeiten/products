<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Event\ModifyCategoryQueryEvent;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @extends AbstractReadOnlyRepository<Category>
 */
final class CategoryRepository extends AbstractReadOnlyRepository
{
    /**
     * "uid" breaks ties for rows sharing the same "sorting" value.
     *
     * @var array<non-empty-string, QueryInterface::ORDER_*>
     */
    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING,
    ];

    /**
     * @return Category[]
     */
    public function findAllIgnoringStoragePage(): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $this->eventDispatcher->dispatch(new ModifyCategoryQueryEvent($query));
        return $query->execute()->toArray();
    }

    public function findByUidIgnoringStoragePage(int $uid): ?Category
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('uid', $uid));
        /** @var Category|null $category */
        $category = $query->execute()->getFirst();
        return $category;
    }
}
