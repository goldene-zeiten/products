<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\TransactionFixture;

/**
 * Fixture-only failure, raised from inside the order-placement transaction.
 */
final class PlacementAbortedException extends \RuntimeException {}
