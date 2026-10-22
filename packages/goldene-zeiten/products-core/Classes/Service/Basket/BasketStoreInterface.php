<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;

/**
 * Where the basket of a request is kept when it is not the frontend user's session, for example a basket an
 * integration builds for a customer who is not browsing. Hand it to the request as the attribute
 * {@see BasketStorage::STORE_ATTRIBUTE} and {@see BasketService} reads and writes it instead.
 */
interface BasketStoreInterface
{
    public function load(): Basket;

    public function save(Basket $basket): void;
}
