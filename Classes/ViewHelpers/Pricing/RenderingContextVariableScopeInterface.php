<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\ViewHelpers\Pricing;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

interface RenderingContextVariableScopeInterface
{
    public function setVariable(?RenderingContextInterface $renderingContext, string $name, mixed $value): void;

    public function removeVariable(?RenderingContextInterface $renderingContext, string $name): void;
}
