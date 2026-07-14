<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Export\Exception;

/**
 * Thrown when an exporter is requested for an order it denied itself for during discovery.
 */
final class OrderExporterNotAvailableException extends \RuntimeException {}
