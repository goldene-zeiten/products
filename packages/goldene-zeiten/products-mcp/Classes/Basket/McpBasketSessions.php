<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotFoundException;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotUpdatedException;
use TYPO3\CMS\Core\Session\Backend\SessionBackendInterface;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Keeps the baskets assistants build in the session backend configured as "products_mcp_basket". A basket is
 * addressed by a random token; it stays valid for "products.mcp.checkoutLinkLifetime" seconds after its last
 * change, which is also how long a checkout link handed out for it works.
 */
final readonly class McpBasketSessions
{
    public const SESSION_IDENTIFIER = 'products_mcp_basket';

    private const TOKEN_PATTERN = '/^[0-9a-f]{32}$/';

    public function __construct(
        private SessionManager $sessionManager,
        private Context $context
    ) {}

    public function create(Site $site): string
    {
        $backend = $this->backend();
        $backend->collectGarbage($this->lifetime($site));
        $token = bin2hex(random_bytes(16));
        try {
            $backend->set($token, ['ses_data' => $this->encode(new Basket())]);
        } catch (SessionNotCreatedException $exception) {
            throw new ToolFailedException('The basket could not be created.', 1792711026, $exception);
        }

        return $token;
    }

    /**
     * The basket behind a token, or null when the token is not one of a basket, or the basket has expired.
     */
    public function find(string $token, Site $site): ?Basket
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }
        try {
            $record = $this->backend()->get($token);
        } catch (SessionNotFoundException) {
            return null;
        }
        if ((int)$record['ses_tstamp'] + $this->lifetime($site) < $this->now()) {
            return null;
        }

        return $this->decode((string)$record['ses_data']);
    }

    /**
     * Also renews the lifetime of the basket.
     */
    public function save(string $token, Basket $basket): void
    {
        try {
            $this->backend()->update($token, ['ses_data' => $this->encode($basket)]);
        } catch (SessionNotUpdatedException $exception) {
            throw new ToolFailedException('The basket could not be saved.', 1792711027, $exception);
        }
    }

    private function backend(): SessionBackendInterface
    {
        return $this->sessionManager->getSessionBackend(self::SESSION_IDENTIFIER);
    }

    /**
     * Seconds a basket, and a checkout link for it, stays valid after the last change.
     */
    public function lifetime(Site $site): int
    {
        return max(60, (int)$site->getSettings()->get('products.mcp.checkoutLinkLifetime', 86400));
    }

    private function now(): int
    {
        return (int)$this->context->getPropertyFromAspect('date', 'timestamp');
    }

    private function encode(Basket $basket): string
    {
        $items = [];
        foreach ($basket->getItems() as $item) {
            $items[] = [
                'productUid' => $item->getProductUid(),
                'articleUid' => $item->getArticleUid(),
                'quantity' => $item->getQuantity(),
                'selections' => $item->getSelections(),
            ];
        }

        return json_encode(['items' => $items], JSON_THROW_ON_ERROR);
    }

    private function decode(string $data): Basket
    {
        $decoded = json_decode($data, true);
        $items = [];
        foreach (is_array($decoded) ? ($decoded['items'] ?? []) : [] as $item) {
            $items[] = new BasketItem(
                (int)$item['productUid'],
                isset($item['articleUid']) ? (int)$item['articleUid'] : null,
                (int)$item['quantity'],
                array_map('strval', $item['selections'] ?? [])
            );
        }

        return new Basket($items);
    }
}
