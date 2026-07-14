<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Basket;

use GoldeneZeiten\Products\Domain\Dto\Basket;
use GoldeneZeiten\Products\Domain\Dto\BasketItem;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class BasketStorage
{
    private const SESSION_KEY = 'tx_products_basket';

    public function load(ServerRequestInterface $request): Basket
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return new Basket();
        }

        $data = $frontendUser->getKey('ses', self::SESSION_KEY);
        if (empty($data)) {
            return new Basket();
        }

        $sessionData = json_decode((string)$data, true);
        if (!is_array($sessionData)) {
            return new Basket();
        }

        $items = [];
        foreach ($sessionData['items'] ?? [] as $itemData) {
            $items[] = new BasketItem(
                (int)($itemData['productUid'] ?? 0),
                isset($itemData['articleUid']) ? (int)$itemData['articleUid'] : null,
                (int)($itemData['quantity'] ?? 0)
            );
        }
        return new Basket($items);
    }

    public function save(ServerRequestInterface $request, Basket $basket): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return;
        }

        $itemsData = [];
        foreach ($basket->getItems() as $item) {
            $itemsData[] = [
                'productUid' => $item->getProductUid(),
                'articleUid' => $item->getArticleUid(),
                'quantity' => $item->getQuantity(),
            ];
        }
        $sessionData = ['items' => $itemsData];

        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode($sessionData));
        $frontendUser->storeSessionData();
    }
}
