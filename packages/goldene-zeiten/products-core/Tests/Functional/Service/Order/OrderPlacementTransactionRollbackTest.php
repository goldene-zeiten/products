<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Order\OrderPlacementTransaction;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\TransactionFixture\AbortPlacementListener;
use GoldeneZeiten\Products\TransactionFixture\PlacementAbortedException;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * Order placement writes the order, decrements stock and burns vouchers. These writes must be atomic: a
 * failure part-way through may not leave a burned voucher behind for an order that does not exist.
 *
 * {@see OrderPlacementTransaction::run()} is the only production entry point into order creation and the
 * one that owns the transaction, so it - not the creation service beneath it - is what has to be proven
 * to roll back.
 *
 * The fixture listener aborts the placement from inside that transaction, after every one of those writes
 * has already happened, so what these tests assert is that they are undone, not that they never ran.
 */
final class OrderPlacementTransactionRollbackTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-transaction-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/order_placement_with_voucher.csv');
        AbortPlacementListener::$armed = true;
    }

    protected function tearDown(): void
    {
        AbortPlacementListener::$armed = false;
        parent::tearDown();
    }

    #[Test]
    public function aFailureInsideThePlacementLeavesNoOrderBehind(): void
    {
        $this->placeAndExpectAbort();

        $this->assertSame(0, $this->countRows('tx_products_domain_model_order'));
    }

    #[Test]
    public function aFailureInsideThePlacementLeavesNoVoucherRedemptionBehind(): void
    {
        $this->placeAndExpectAbort();

        // Only the unrelated row the fixture ships with (uid 1, for the exhausted voucher) survives.
        $this->assertSame(1, $this->countRows('tx_products_domain_model_voucherredemption'));
    }

    #[Test]
    public function aFailureInsideThePlacementDoesNotBurnTheVoucher(): void
    {
        $this->placeAndExpectAbort();

        $this->assertSame(0, $this->voucherRedemptionCount(1));
    }

    #[Test]
    public function aFailureInsideThePlacementRestoresTheDecrementedStock(): void
    {
        $this->placeAndExpectAbort();

        $this->assertSame(100, $this->productStock(1));
    }

    private function placeAndExpectAbort(): void
    {
        $subject = $this->get(OrderPlacementTransaction::class);

        try {
            $subject->run(
                $this->request(),
                $this->basketViewModel($this->product()),
                new CheckoutSelections(['SAVE10'], 0),
                $this->address(),
                $this->paymentMethod()
            );
            $this->fail('Expected the fixture listener to abort the placement.');
        } catch (PlacementAbortedException) {
            // expected: raised from inside the placement transaction
        }
    }

    private function countRows(string $table): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')->from($table)->executeQuery()->fetchOne();
    }

    private function voucherRedemptionCount(int $voucherUid): int
    {
        $table = 'tx_products_domain_model_voucher';
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('redemption_count')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($voucherUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function productStock(int $productUid): int
    {
        $table = 'tx_products_domain_model_product';
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('in_stock')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    private function product(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function basketViewModel(Product $product): BasketViewModel
    {
        $unitPriceNet = Money::fromDecimalString('84.03');
        $unitPriceGross = Money::fromDecimalString('100.00');
        $item = new BasketViewItem(
            $product,
            null,
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

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }

    private function paymentMethod(): PaymentMethodInterface
    {
        return $this->get(PaymentMethodRegistry::class)->get('invoice');
    }
}
