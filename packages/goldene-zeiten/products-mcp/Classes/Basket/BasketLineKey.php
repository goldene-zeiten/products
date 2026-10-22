<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Basket;

use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;

/**
 * The name of a basket line an assistant refers to: product uid, article uid (0 without a variant) and the
 * key of the line's option selections, joined by colons, for example "3:71:" or "5:0:9f2c41d07a3b6e18".
 */
final class BasketLineKey
{
    public static function format(int $productUid, ?int $articleUid, string $optionsKey): string
    {
        return $productUid . ':' . ($articleUid ?? 0) . ':' . $optionsKey;
    }

    /**
     * @return array{product: int, article: int|null, optionsKey: string}
     * @throws ToolFailedException
     */
    public static function parse(mixed $line): array
    {
        if (!is_string($line) || preg_match('/^(\d+):(\d+):([0-9a-f]*)$/', $line, $parts) !== 1) {
            throw new ToolFailedException(
                'The line has to be a line key as get_basket returns it, such as "3:71:".',
                1792711029
            );
        }

        return [
            'product' => (int)$parts[1],
            'article' => (int)$parts[2] > 0 ? (int)$parts[2] : null,
            'optionsKey' => $parts[3],
        ];
    }
}
