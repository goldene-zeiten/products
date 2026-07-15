<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Core13\ViewHelpers\Format;

use GoldeneZeiten\Products\Core\ViewHelpers\Format\RenderingContextRequestResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

#[AsAlias(RenderingContextRequestResolverInterface::class)]
final class RenderingContextRequestResolver implements RenderingContextRequestResolverInterface
{
    public function resolveRequest(?RenderingContextInterface $renderingContext): ?ServerRequestInterface
    {
        if ($renderingContext === null || !$renderingContext->hasAttribute(ServerRequestInterface::class)) {
            return null;
        }

        return $renderingContext->getAttribute(ServerRequestInterface::class);
    }
}
