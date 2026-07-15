<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Lets add-ons contribute extra template variables to the product list and detail views without the core
 * catalog controller knowing about them. An add-on listens, reads what it needs from the request, and adds
 * its variables; the controller merges them into the view. With no listener the views simply render with
 * their own variables only.
 *
 * @see \GoldeneZeiten\Products\Core\Controller\ProductController
 */
final class EnrichProductViewEvent
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        private readonly ServerRequestInterface $request,
        private array $variables = [],
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function addVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }
}
