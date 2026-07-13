<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core14\ViewHelpers\Pricing;

use GoldeneZeiten\Products\ViewHelpers\Pricing\RenderingContextVariableScopeInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

#[AsAlias(RenderingContextVariableScopeInterface::class)]
final class RenderingContextVariableScope implements RenderingContextVariableScopeInterface
{
    public function setVariable(?RenderingContextInterface $renderingContext, string $name, mixed $value): void
    {
        $renderingContext?->getVariableProvider()->add($name, $value);
    }

    public function removeVariable(?RenderingContextInterface $renderingContext, string $name): void
    {
        $renderingContext?->getVariableProvider()->remove($name);
    }
}
