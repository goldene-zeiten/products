<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Service\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Core\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\Core\Service\Basket\BasketStoreInterface;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BasketStorageStoreTest extends UnitTestCase
{
    #[Test]
    public function aStoreOnTheRequestReplacesTheFrontendUserSession(): void
    {
        $store = new class () implements BasketStoreInterface {
            public ?Basket $saved = null;

            public function load(): Basket
            {
                return new Basket([new BasketItem(7, null, 2)]);
            }

            public function save(Basket $basket): void
            {
                $this->saved = $basket;
            }
        };
        $request = (new ServerRequest())->withAttribute(BasketStorage::STORE_ATTRIBUTE, $store);
        $subject = new BasketStorage();

        $loaded = $subject->load($request);
        $subject->save($request, new Basket([new BasketItem(9, null, 1)]));

        $this->assertSame(2, $loaded->getQuantity(7, null));
        $this->assertSame(1, $store->saved?->getQuantity(9, null));
    }

    #[Test]
    public function withoutAStoreAndFrontendUserTheBasketIsEmpty(): void
    {
        $this->assertTrue((new BasketStorage())->load(new ServerRequest())->isEmpty());
    }
}
