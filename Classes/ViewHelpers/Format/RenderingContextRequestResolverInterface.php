<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Format;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * AbstractViewHelper::$renderingContext is typed non-nullable (docblock-only property) on
 * TYPO3 13's typo3fluid/fluid and natively nullable on TYPO3 14's - callers can't write a single
 * null-check that satisfies both. The Core13/Core14 implementations absorb that difference.
 */
interface RenderingContextRequestResolverInterface
{
    public function resolveRequest(?RenderingContextInterface $renderingContext): ?ServerRequestInterface;
}
