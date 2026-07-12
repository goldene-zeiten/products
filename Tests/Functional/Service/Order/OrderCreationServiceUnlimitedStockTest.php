<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Order\Exception\InsufficientStockException;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

final class OrderCreationServiceUnlimitedStockTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderCreationServiceUnlimitedStockTest/order_placement_unlimited_stock.csv');
        // Extbase setting reads in OrderFactory constructor need a request resolvable via globals.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    #[DataProvider('unlimitedStockScenarioProvider')]
    public function placementSucceedsWithUnlimitedStockScenarios(int $productUid, ?int $articleUid, string $resultFixturePath): void
    {
        $subject = $this->subject();

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel($this->product($productUid), $articleUid !== null ? $this->article($articleUid) : null),
            $this->noSelections(),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertNotNull($order->getUid());
        $this->assertCSVDataSet($resultFixturePath);
    }

    #[Test]
    public function placementThrowsForALimitedStockProductWithInsufficientStock(): void
    {
        $subject = $this->subject();
        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionCode(1751751020);

        $subject->create(
            $this->request(),
            $this->basketViewModel($this->product(2), null),
            $this->noSelections(),
            $this->address(),
            $this->paymentMethod()
        );
    }

    /**
     * @return \Generator<string, array<string, mixed>>
     */
    public static function unlimitedStockScenarioProvider(): \Generator
    {
        yield 'unlimitedProduct' => ['productUid' => 1, 'articleUid' => null, 'resultFixturePath' => __DIR__ . '/Fixtures/Result/unlimited_stock_product_zero_stock.csv'];
        yield 'unlimitedArticle' => ['productUid' => 3, 'articleUid' => 1, 'resultFixturePath' => __DIR__ . '/Fixtures/Result/unlimited_stock_article_zero_stock.csv'];
        yield 'unlimitedProductWithLimitedArticle' => ['productUid' => 4, 'articleUid' => 2, 'resultFixturePath' => __DIR__ . '/Fixtures/Result/unlimited_stock_article_2_zero_stock.csv'];
    }

    private function subject(): OrderCreationService
    {
        return $this->get(OrderCreationService::class);
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    private function basketViewModel(Product $product, ?Article $article): BasketViewModel
    {
        $unitPriceNet = Money::fromDecimalString('84.03');
        $unitPriceGross = Money::fromDecimalString('100.00');
        $item = new BasketViewItem(
            $product,
            $article,
            1,
            $unitPriceNet,
            $unitPriceGross,
            0.19,
            $unitPriceNet,
            $unitPriceGross,
            $unitPriceGross->subtract($unitPriceNet)
        );
        return new BasketViewModel([$item], $unitPriceNet, $unitPriceGross, $unitPriceGross->subtract($unitPriceNet), 'EUR');
    }

    private function noSelections(): CheckoutSelections
    {
        return new CheckoutSelections([], 0);
    }

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }

    private function paymentMethod(): PaymentMethodInterface
    {
        return $this->get(PaymentMethodRegistry::class)->get('invoice');
    }

    private function product(int $uid): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function article(int $uid): Article
    {
        $article = $this->get(ArticleRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Article::class, $article);
        return $article;
    }
}
