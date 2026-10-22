<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Mcp\Basket\BasketAccess;
use GoldeneZeiten\Products\Mcp\Basket\McpBasketSessions;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Handoff\CheckoutLinkSigner;
use GoldeneZeiten\Products\Mcp\Middleware\EndpointPaths;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;
use TYPO3\CMS\Core\Context\Context;

/**
 * Hands the basket over to the customer: returns a link that puts the basket into the customer's own session
 * and opens the checkout, where terms, withdrawal and payment are dealt with as for any other order.
 */
final readonly class StartCheckoutTool implements McpToolInterface
{
    public function __construct(
        private BasketAccess $access,
        private McpBasketSessions $sessions,
        private CheckoutLinkSigner $signer,
        private EndpointPaths $paths,
        private Context $context
    ) {}

    public function getName(): string
    {
        return 'start_checkout';
    }

    public function getDescription(): string
    {
        return 'Returns a checkoutUrl for the customer to open. It loads the basket into their session and '
            . 'continues in the shop checkout. The link expires; the order is placed by the customer there.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'basketToken' => ['type' => 'string', 'description' => 'The token create_basket returned.'],
            ],
            'required' => ['basketToken'],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $open = $this->access->open($arguments['basketToken'] ?? null, $context);
        if ($open->basket->isEmpty()) {
            throw new ToolFailedException('The basket is empty; add products before starting the checkout.', 1792711038);
        }
        $this->sessions->save($open->token, $open->basket);
        $expiresAt = (int)$this->context->getPropertyFromAspect('date', 'timestamp') + $this->sessions->lifetime($context->site);

        return [
            'checkoutUrl' => $this->paths->checkoutUrl($context->site, $context->request, $this->signer->issue($open->token, $expiresAt)),
            'expiresAt' => gmdate('c', $expiresAt),
        ];
    }
}
