<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller\Exception;

use TYPO3\CMS\Core\Error\Http\PageNotFoundException;

/**
 * Thrown when a product visibility checker denies access to a product.
 * Extends PageNotFoundException so the 404 handler catches it.
 */
final class ProductNotVisibleException extends PageNotFoundException {}
