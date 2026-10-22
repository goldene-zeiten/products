<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Mcp\Basket\BasketAccess;
use GoldeneZeiten\Products\Mcp\Basket\BasketLineGuard;
use GoldeneZeiten\Products\Mcp\Basket\BasketLineKey;
use GoldeneZeiten\Products\Mcp\Basket\BasketMapper;
use GoldeneZeiten\Products\Mcp\Basket\OpenBasket;
use GoldeneZeiten\Products\Mcp\Catalog\ProductFinder;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Sets the quantity of a line of an assistant basket, or removes the line with quantity 0.
 */
final readonly class UpdateBasketItemTool implements McpToolInterface
{
    public function __construct(
        private BasketAccess $access,
        private BasketService $basketService,
        private BasketLineGuard $guard,
        private ProductFinder $productFinder,
        private BasketMapper $mapper
    ) {}

    public function getName(): string
    {
        return 'update_basket_item';
    }

    public function getDescription(): string
    {
        return 'Sets the quantity of a basket line, named by the "line" key get_basket returns, and returns '
            . 'the basket. Quantity 0 removes the line.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'basketToken' => ['type' => 'string', 'description' => 'The token create_basket returned.'],
                'line' => ['type' => 'string', 'description' => 'The "line" key of the line, as get_basket returns it.'],
                'quantity' => ['type' => 'integer', 'minimum' => 0, 'description' => 'The new quantity; 0 removes the line.'],
            ],
            'required' => ['basketToken', 'line', 'quantity'],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $open = $this->access->open($arguments['basketToken'] ?? null, $context);
        $line = BasketLineKey::parse($arguments['line'] ?? null);
        $quantity = max(0, (int)($arguments['quantity'] ?? 0));
        $current = $this->currentQuantity($open, $line);
        if ($quantity === 0) {
            $this->basketService->remove($open->request, $line['product'], $line['article'], $line['optionsKey']);
        } else {
            $this->assertAllowed($open, $line, $current, $quantity, $context);
            $this->basketService->update($open->request, $line['product'], $line['article'], $quantity, $line['optionsKey']);
        }

        return $this->mapper->map($this->access->open($open->token, $context));
    }

    /**
     * @param array{product: int, article: int|null, optionsKey: string} $line
     * @throws ToolFailedException when the basket has no such line
     */
    private function currentQuantity(OpenBasket $open, array $line): int
    {
        $quantity = $open->basket->getQuantity($line['product'], $line['article'], $line['optionsKey']);
        if ($quantity === 0) {
            throw new ToolFailedException('The basket has no such line; take the line key from get_basket.', 1792711036);
        }

        return $quantity;
    }

    /**
     * @param array{product: int, article: int|null, optionsKey: string} $line
     */
    private function assertAllowed(OpenBasket $open, array $line, int $current, int $quantity, McpContext $context): void
    {
        $product = $this->productFinder->require($line['product'], $context);
        $article = $this->guard->article($product, $line['article']);
        $held = $this->guard->heldQuantity($open->basket, $line['product'], $line['article']);
        $this->guard->assertQuantity($product, $article, $held - $current + $quantity);
    }
}
