<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Service\Basket\BasketStoreInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * One assistant basket as the basket store of a request, so the shop's basket service works on it like on a
 * customer's own basket.
 */
final readonly class McpBasketStore implements BasketStoreInterface
{
    public function __construct(
        private McpBasketSessions $sessions,
        private string $token,
        private Site $site
    ) {}

    public function load(): Basket
    {
        return $this->sessions->find($this->token, $this->site) ?? new Basket();
    }

    public function save(Basket $basket): void
    {
        $this->sessions->save($this->token, $basket);
    }
}
