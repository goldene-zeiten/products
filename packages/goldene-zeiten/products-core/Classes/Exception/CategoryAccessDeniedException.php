<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Exception;

/**
 * Thrown when a DataHandler write targets a category branch outside the
 * acting backend user's resolved category mounts.
 */
final class CategoryAccessDeniedException extends \RuntimeException {}
