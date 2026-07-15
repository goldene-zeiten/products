<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Exception;

/**
 * Thrown when a DHL Express rate request cannot be completed. The shipping provider catches it and offers
 * no options, so the table-rate fallback serves the basket instead of the checkout breaking.
 */
final class DhlExpressRatingException extends \RuntimeException {}
