<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Exception;

/**
 * Thrown when a price period being saved overlaps with an existing period
 * for the same parent (product or article) in the same fe_group scope.
 */
final class PricePeriodOverlapException extends \RuntimeException {}
