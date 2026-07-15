<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller\Exception;

use TYPO3\CMS\Core\Error\Http\PageNotFoundException;

/**
 * Thrown when a URL path doesn't match the product's (or its selected article's) actual category assignment.
 * Extends PageNotFoundException so the 404 handler catches it.
 */
final class ProductPathMismatchException extends PageNotFoundException {}
