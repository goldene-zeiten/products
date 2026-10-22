<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Basket\Exception\LineItemOptionsRejectedException;
use GoldeneZeiten\Products\Mcp\Basket\BasketAccess;
use GoldeneZeiten\Products\Mcp\Basket\BasketLineGuard;
use GoldeneZeiten\Products\Mcp\Basket\BasketMapper;
use GoldeneZeiten\Products\Mcp\Catalog\ProductFinder;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Puts a product or one of its variants into an assistant basket, under the rules of the shop's own basket:
 * the product has to be visible, a product with variants needs one, and the quantity has to be within the
 * basket limits and the stock. Adding what the basket already holds raises its line.
 */
final readonly class AddToBasketTool implements McpToolInterface
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
        return 'add_to_basket';
    }

    public function getDescription(): string
    {
        return 'Adds a product to a basket and returns the basket. A product with variants needs the uid of '
            . 'one variant as "article". A refused addition, for example for lack of stock, is an error '
            . 'that says why.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'basketToken' => ['type' => 'string', 'description' => 'The token create_basket returned.'],
                'product' => ['type' => 'integer', 'description' => 'Uid of the product.'],
                'article' => ['type' => 'integer', 'description' => 'Uid of the variant, for a product with variants.'],
                'quantity' => ['type' => 'integer', 'minimum' => 1, 'description' => 'How many to add, 1 by default.'],
                'selections' => [
                    'type' => 'object',
                    'description' => 'Options of the line such as an engraving, by option identifier.',
                    'additionalProperties' => ['type' => 'string'],
                ],
            ],
            'required' => ['basketToken', 'product'],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $open = $this->access->open($arguments['basketToken'] ?? null, $context);
        $product = $this->productFinder->require((int)($arguments['product'] ?? 0), $context);
        $articleUid = isset($arguments['article']) ? (int)$arguments['article'] : null;
        $article = $this->guard->article($product, $articleUid);
        $quantity = max(1, (int)($arguments['quantity'] ?? 1));
        $held = $this->guard->heldQuantity($open->basket, (int)$product->getUid(), $articleUid);
        $this->guard->assertQuantity($product, $article, $held + $quantity);
        $selections = array_map('strval', is_array($arguments['selections'] ?? null) ? $arguments['selections'] : []);
        try {
            $this->basketService->add($open->request, (int)$product->getUid(), $articleUid, $quantity, $selections);
        } catch (LineItemOptionsRejectedException $exception) {
            throw new ToolFailedException($exception->getMessage(), 1792711035, $exception);
        }

        return $this->mapper->map($this->access->open($open->token, $context));
    }
}
