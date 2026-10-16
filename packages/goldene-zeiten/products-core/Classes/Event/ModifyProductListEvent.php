<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Catalog\ProductListService;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListCriteria;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Lets integrators narrow a built-in product list before it is queried and paged - hide out-of-region
 * items or restrict a list to a brand. Build the constraint with {@see getQuery()} and hand it to
 * {@see addConstraint()}; the list shows the products matching every added constraint. Because the
 * constraints become part of the query, page counts stay right.
 *
 * Listings registered by an add-on through ProductListModeProviderInterface select their products
 * themselves and do not dispatch this event.
 *
 * @see ProductListService::paginate()
 */
final class ModifyProductListEvent
{
    /**
     * @var ConstraintInterface[]
     */
    private array $constraints = [];

    /**
     * @param QueryInterface<Product> $query
     */
    public function __construct(
        private readonly QueryInterface $query,
        private readonly ProductListCriteria $criteria,
        private readonly ServerRequestInterface $request,
    ) {}

    /**
     * @return QueryInterface<Product>
     */
    public function getQuery(): QueryInterface
    {
        return $this->query;
    }

    public function getCriteria(): ProductListCriteria
    {
        return $this->criteria;
    }

    public function addConstraint(ConstraintInterface $constraint): void
    {
        $this->constraints[] = $constraint;
    }

    /**
     * @return ConstraintInterface[]
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
