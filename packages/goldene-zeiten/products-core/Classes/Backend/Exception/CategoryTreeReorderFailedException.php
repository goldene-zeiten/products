<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend\Exception;

/**
 * Raised when DataHandler refuses or fails a reorder from the category tree, e.g. for lack of
 * permission.
 */
final class CategoryTreeReorderFailedException extends \RuntimeException {}
