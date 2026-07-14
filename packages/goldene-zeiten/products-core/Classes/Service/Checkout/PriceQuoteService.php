<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Checkout;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Order\Exception\PriceQuoteExpiredException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Freezes the basket price a customer is shown on the checkout review step, and enforces
 * it (rather than a possibly-changed live price) at order-placement time, within an
 * integrator-configurable validity window - see EU Consumer Rights Directive 2011/83/EU
 * Art. 8(2) / German §312j BGB: the price shown directly before the customer places a
 * binding order must be the price that's charged.
 */
final class PriceQuoteService
{
    private const SESSION_KEY = 'tx_products_checkout_price_quote';

    public function freeze(ServerRequestInterface $request, BasketViewModel $basketViewModel): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return;
        }

        $quote = [
            'timestamp' => time(),
            'currency' => $basketViewModel->getCurrency(),
            'totalNet' => $basketViewModel->getTotalNet()->getCents(),
            'totalGross' => $basketViewModel->getTotalGross()->getCents(),
            'totalTax' => $basketViewModel->getTotalTax()->getCents(),
            'items' => array_map(
                static fn(BasketViewItem $item): array => [
                    'productUid' => $item->getProduct()->getUid(),
                    'articleUid' => $item->getArticle()?->getUid(),
                    'quantity' => $item->getQuantity(),
                    'unitPriceNet' => $item->getUnitPriceNet()->getCents(),
                    'unitPriceGross' => $item->getUnitPriceGross()->getCents(),
                    'taxRate' => $item->getTaxRate(),
                    'lineTotalNet' => $item->getLineTotalNet()->getCents(),
                    'lineTotalGross' => $item->getLineTotalGross()->getCents(),
                    'lineTotalTax' => $item->getLineTotalTax()->getCents(),
                ],
                $basketViewModel->getItems()
            ),
        ];

        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode($quote));
        $frontendUser->storeSessionData();
    }

    /**
     * @throws PriceQuoteExpiredException if a quote was frozen but the basket has since
     *         changed, or the quote has expired - the caller (OrderPlacementService) lets
     *         this bubble; CheckoutController::finalizeAction() already redirects any
     *         OrderPlacementExceptionInterface back to the review step.
     */
    public function resolve(ServerRequestInterface $request, BasketViewModel $liveBasketViewModel, ProductsConfiguration $configuration): BasketViewModel
    {
        $quote = $this->loadQuote($request);
        if ($quote === null) {
            // Never reviewed via the browser flow (e.g. a programmatic order placement) -
            // proceed with live pricing, same as before this mechanism existed.
            return $liveBasketViewModel;
        }

        $liveFingerprint = self::fingerprint($liveBasketViewModel->getItems());
        $quoteFingerprint = array_map(
            static fn(array $line): string => self::fingerprintLine((int)$line['productUid'], $line['articleUid'] !== null ? (int)$line['articleUid'] : null, (int)$line['quantity']),
            $quote['items']
        );
        sort($liveFingerprint);
        sort($quoteFingerprint);
        if ($liveFingerprint !== $quoteFingerprint) {
            throw new PriceQuoteExpiredException(
                'Your basket has changed since you last reviewed your order. Please review your order again.',
                1751950000
            );
        }

        if (time() - (int)$quote['timestamp'] > $configuration->getPriceQuoteValiditySeconds()) {
            throw new PriceQuoteExpiredException(
                'Your reviewed price has expired. Please review your order again.',
                1751950001
            );
        }

        return $this->buildFromQuote($liveBasketViewModel, $quote);
    }

    public function clear(ServerRequestInterface $request): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY, null);
            $frontendUser->storeSessionData();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadQuote(ServerRequestInterface $request): ?array
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return null;
        }
        $data = $frontendUser->getKey('ses', self::SESSION_KEY);
        if (empty($data)) {
            return null;
        }
        $quote = json_decode((string)$data, true);
        return is_array($quote) ? $quote : null;
    }

    /**
     * @param array<BasketViewItem> $items
     * @return string[]
     */
    private static function fingerprint(array $items): array
    {
        return array_map(
            static fn(BasketViewItem $item): string => self::fingerprintLine((int)$item->getProduct()->getUid(), $item->getArticle()?->getUid(), $item->getQuantity()),
            $items
        );
    }

    private static function fingerprintLine(int $productUid, ?int $articleUid, int $quantity): string
    {
        return sprintf('%d:%s:%d', $productUid, $articleUid !== null ? (string)$articleUid : '', $quantity);
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function buildFromQuote(BasketViewModel $liveBasketViewModel, array $quote): BasketViewModel
    {
        $quoteLinesByKey = [];
        foreach ($quote['items'] as $line) {
            $key = self::fingerprintLine((int)$line['productUid'], $line['articleUid'] !== null ? (int)$line['articleUid'] : null, (int)$line['quantity']);
            $quoteLinesByKey[$key] = $line;
        }

        $items = [];
        foreach ($liveBasketViewModel->getItems() as $liveItem) {
            $key = self::fingerprintLine((int)$liveItem->getProduct()->getUid(), $liveItem->getArticle()?->getUid(), $liveItem->getQuantity());
            $line = $quoteLinesByKey[$key];
            $items[] = new BasketViewItem(
                $liveItem->getProduct(),
                $liveItem->getArticle(),
                $liveItem->getQuantity(),
                Money::fromCents((int)$line['unitPriceNet']),
                Money::fromCents((int)$line['unitPriceGross']),
                (float)$line['taxRate'],
                Money::fromCents((int)$line['lineTotalNet']),
                Money::fromCents((int)$line['lineTotalGross']),
                Money::fromCents((int)$line['lineTotalTax'])
            );
        }

        return new BasketViewModel(
            $items,
            Money::fromCents((int)$quote['totalNet']),
            Money::fromCents((int)$quote['totalGross']),
            Money::fromCents((int)$quote['totalTax']),
            (string)$quote['currency']
        );
    }
}
