<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\ShippingPoint;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @extends AbstractReadOnlyRepository<ShippingPoint>
 */
final class ShippingPointRepository extends AbstractReadOnlyRepository
{
    protected $defaultOrderings = ['uid' => QueryInterface::ORDER_ASCENDING];
}
