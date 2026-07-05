<?php
declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Basket;

use GoldeneZeiten\Products\Domain\Dto\Basket;
use GoldeneZeiten\Products\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\TaxService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class BasketService
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly TaxService $taxService,
        private readonly BasketStorage $basketStorage,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function add(ServerRequestInterface $request, int $productUid, ?int $articleUid, int $quantity): void
    {
        $product = $this->productRepository->findByUid($productUid);
        if (!$product instanceof Product) {
            return;
        }

        if ($articleUid === null && $product->getArticles()->count() > 0) {
            // A product with articles is purchasable only via one of its articles.
            return;
        }

        $basket = $this->basketStorage->load($request);
        $basket->addItem(new BasketItem($productUid, $articleUid, $quantity));
        $this->basketStorage->save($request, $basket);
    }

    public function update(ServerRequestInterface $request, int $productUid, ?int $articleUid, int $quantity): void
    {
        $basket = $this->basketStorage->load($request);
        $basket->updateQuantity($productUid, $articleUid, $quantity);
        $this->basketStorage->save($request, $basket);
    }

    public function remove(ServerRequestInterface $request, int $productUid, ?int $articleUid): void
    {
        $basket = $this->basketStorage->load($request);
        $basket->removeItem($productUid, $articleUid);
        $this->basketStorage->save($request, $basket);
    }

    public function clear(ServerRequestInterface $request): void
    {
        $this->basketStorage->save($request, new Basket());
    }

    public function getBasketViewModel(ServerRequestInterface $request): BasketViewModel
    {
        $basket = $this->basketStorage->load($request);
        $viewItems = [];
        $totalNetCents = 0;
        $totalGrossCents = 0;
        $totalTaxCents = 0;

        $pricingMode = (string)($this->settings['pricing']['mode'] ?? 'gross');
        $currency = (string)($this->settings['pricing']['currency'] ?? 'EUR');

        foreach ($basket->getItems() as $item) {
            $product = $this->productRepository->findByUid($item->getProductUid());
            if (!$product instanceof Product) {
                continue;
            }

            $article = null;
            if ($item->getArticleUid() !== null) {
                $article = $this->articleRepository->findByUid($item->getArticleUid());
            }

            // Price resolution
            $basePrice = $product->getPrice();
            if ($article !== null && $article->getPrice()->getCents() > 0) {
                $basePrice = $article->getPrice();
            }

            $taxRate = $this->taxService->getTaxRate($product->getTaxClass());

            if ($pricingMode === 'gross') {
                $unitPriceGross = $basePrice;
                $unitPriceNet = Money::fromCents((int)round($unitPriceGross->getCents() / (1 + $taxRate)));
            } else {
                $unitPriceNet = $basePrice;
                $unitPriceGross = Money::fromCents((int)round($unitPriceNet->getCents() * (1 + $taxRate)));
            }

            $lineTotalNet = $unitPriceNet->multiply($item->getQuantity());
            $lineTotalGross = $unitPriceGross->multiply($item->getQuantity());
            $lineTotalTax = $lineTotalGross->subtract($lineTotalNet);

            $viewItems[] = new BasketViewItem(
                $product,
                $article,
                $item->getQuantity(),
                $unitPriceNet,
                $unitPriceGross,
                $taxRate,
                $lineTotalNet,
                $lineTotalGross,
                $lineTotalTax
            );

            $totalNetCents += $lineTotalNet->getCents();
            $totalGrossCents += $lineTotalGross->getCents();
            $totalTaxCents += $lineTotalTax->getCents();
        }

        return new BasketViewModel(
            $viewItems,
            Money::fromCents($totalNetCents),
            Money::fromCents($totalGrossCents),
            Money::fromCents($totalTaxCents),
            $currency
        );
    }
}
