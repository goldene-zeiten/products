<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller\Exception;

use TYPO3\CMS\Core\Error\Http\PageNotFoundException;

/**
 * Thrown when a URL path doesn't match the category's actual nesting.
 * Extends PageNotFoundException so the 404 handler catches it.
 */
final class CategoryPathMismatchException extends PageNotFoundException {}
