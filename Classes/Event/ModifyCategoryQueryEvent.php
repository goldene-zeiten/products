<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Repository\CategoryRepository;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Lets integrators constrain or reorder the category query behind the catalogue navigation - restrict by user group,
 * hide seasonal categories, or apply dynamic filters. The query is mutated in place by listeners
 * (e.g. $event->getQuery()->matching(...)).
 *
 * @see CategoryRepository::findAllIgnoringStoragePage()
 */
final class ModifyCategoryQueryEvent
{
    /**
     * @param QueryInterface<Category> $query
     */
    public function __construct(
        private readonly QueryInterface $query
    ) {}

    /**
     * @return QueryInterface<Category>
     */
    public function getQuery(): QueryInterface
    {
        return $this->query;
    }
}
