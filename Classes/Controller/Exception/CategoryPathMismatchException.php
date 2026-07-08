<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller\Exception;

use TYPO3\CMS\Core\Error\Http\PageNotFoundException;

/**
 * Each category path segment is individually validated by the site's route enhancer aspects
 * (a nonexistent slug simply doesn't match any route), but a URL can still combine segments
 * that individually exist yet are not actually nested under one another
 * (e.g. "main-category-1/sub-category-6/last-category-3" mixing a real subcategory with a real
 * leaf category from a different branch). Extending the core 404 exception rather than a plain
 * one means this still resolves to the site's normal "page not found" handling.
 */
final class CategoryPathMismatchException extends PageNotFoundException {}
