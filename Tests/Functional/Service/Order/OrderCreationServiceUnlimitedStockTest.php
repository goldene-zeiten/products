<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use Doctrine\DBAL\ParameterType;
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
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_placement_unlimited_stock.csv');
        // OrderFactory reads Extbase settings eagerly in its constructor, which requires a request
        // to be resolvable via $GLOBALS['TYPO3_REQUEST'] outside of a real controller dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    public function placementSucceedsForAnUnlimitedStockProductDespiteZeroStock(): void
    {
        $order = $this->subject()->create(
            $this->request(),
            $this->basketViewModel($this->product(1), null),
            $this->noSelections(),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertNotNull($order->getUid());
        $this->assertSame(0, $this->currentProductStock(1));
    }

    #[Test]
    public function placementThrowsForALimitedStockProductWithInsufficientStock(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionCode(1751751020);

        $this->subject()->create(
            $this->request(),
            $this->basketViewModel($this->product(2), null),
            $this->noSelections(),
            $this->address(),
            $this->paymentMethod()
        );
    }

    #[Test]
    public function placementSucceedsWhenOnlyTheSelectedArticleIsFlaggedUnlimited(): void
    {
        $order = $this->subject()->create(
            $this->request(),
            $this->basketViewModel($this->product(3), $this->article(1)),
            $this->noSelections(),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertNotNull($order->getUid());
        $this->assertSame(0, $this->currentArticleStock(1));
    }

    #[Test]
    public function placementSucceedsWhenTheProductIsUnlimitedEvenIfItsSelectedArticleIsNot(): void
    {
        $order = $this->subject()->create(
            $this->request(),
            $this->basketViewModel($this->product(4), $this->article(2)),
            $this->noSelections(),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertNotNull($order->getUid());
        $this->assertSame(0, $this->currentArticleStock(2));
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

    private function currentProductStock(int $uid): int
    {
        return $this->currentStock('tx_products_domain_model_product', $uid);
    }

    private function currentArticleStock(int $uid): int
    {
        return $this->currentStock('tx_products_domain_model_article', $uid);
    }

    private function currentStock(string $table, int $uid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        return (int)$queryBuilder
            ->select('in_stock')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();
    }
}
