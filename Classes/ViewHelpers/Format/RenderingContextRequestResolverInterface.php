<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\ViewHelpers\Format;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

interface RenderingContextRequestResolverInterface
{
    public function resolveRequest(?RenderingContextInterface $renderingContext): ?ServerRequestInterface;
}
