<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;

/**
 * Describes an assistant basket the way the basket page prices it: the lines and totals of the basket view
 * model, including line options and their surcharges, for the customer-independent prices of the shop.
 */
final readonly class BasketMapper
{
    public function __construct(
        private BasketService $basketService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function map(OpenBasket $open): array
    {
        $viewModel = $this->basketService->getBasketViewModel($open->request);
        $lines = array_map($this->line(...), $viewModel->getItems());

        return [
            'currency' => $viewModel->getCurrency(),
            'itemCount' => $viewModel->getItemCount(),
            'lines' => $lines,
            'totals' => [
                'net' => $viewModel->getTotalNet()->getDecimalString(),
                'tax' => $viewModel->getTotalTax()->getDecimalString(),
                'gross' => $viewModel->getTotalGross()->getDecimalString(),
            ],
            'vat' => $this->vatBreakdown($viewModel->getItems()),
            'note' => 'Shipping and payment fees are not part of the totals; they are added in the checkout.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(BasketViewItem $item): array
    {
        $product = $item->getProduct();
        $article = $item->getArticle();

        return [
            'line' => BasketLineKey::format((int)$product->getUid(), $article?->getUid(), $item->getOptionsKey()),
            'product' => (int)$product->getUid(),
            'title' => $product->getTitle(),
            'article' => $article instanceof Article
                ? ['uid' => $article->getUid(), 'title' => $article->getTitle(), 'itemNumber' => $article->getItemNumber()]
                : null,
            'options' => array_map(
                static fn(LineItemOption $option): array => [
                    'label' => $option->getLabel(),
                    'value' => $option->getValueLabel(),
                    'surcharge' => $option->getSurcharge()->getDecimalString(),
                ],
                $item->getLineOptions()
            ),
            'quantity' => $item->getQuantity(),
            'vatRate' => round($item->getTaxRate() * 100, 2),
            'unitPrice' => ['net' => $item->getUnitPriceNet()->getDecimalString(), 'gross' => $item->getUnitPriceGross()->getDecimalString()],
            'lineTotal' => ['net' => $item->getLineTotalNet()->getDecimalString(), 'gross' => $item->getLineTotalGross()->getDecimalString()],
        ];
    }

    /**
     * @param BasketViewItem[] $items
     * @return list<array{rate: float, net: string, tax: string}>
     */
    private function vatBreakdown(array $items): array
    {
        $buckets = [];
        foreach ($items as $item) {
            $rate = round($item->getTaxRate() * 100, 2);
            $bucket = $buckets[(string)$rate] ?? ['rate' => $rate, 'net' => Money::fromCents(0), 'tax' => Money::fromCents(0)];
            $buckets[(string)$rate] = [
                'rate' => $rate,
                'net' => $bucket['net']->add($item->getLineTotalNet()),
                'tax' => $bucket['tax']->add($item->getLineTotalTax()),
            ];
        }
        ksort($buckets);
        $breakdown = [];
        foreach ($buckets as $bucket) {
            $breakdown[] = ['rate' => $bucket['rate'], 'net' => $bucket['net']->getDecimalString(), 'tax' => $bucket['tax']->getDecimalString()];
        }

        return $breakdown;
    }
}
