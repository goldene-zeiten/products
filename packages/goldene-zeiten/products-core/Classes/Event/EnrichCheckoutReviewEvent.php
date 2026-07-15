<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Lets add-ons contribute extra template variables to the checkout review page without the core checkout
 * controller knowing about them - a loyalty balance, for instance, that an add-on's review slot then
 * renders. With no listener the review page renders with its own variables only.
 *
 * @see \GoldeneZeiten\Products\Core\Controller\CheckoutController::reviewAction()
 */
final class EnrichCheckoutReviewEvent
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
