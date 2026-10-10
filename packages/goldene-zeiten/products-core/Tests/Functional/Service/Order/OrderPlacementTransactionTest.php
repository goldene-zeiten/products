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
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Order\Exception\PaymentFailedException;
use GoldeneZeiten\Products\Core\Service\Order\OrderPlacementTransaction;
use GoldeneZeiten\Products\PaymentFixture\DecliningGatewayPaymentMethod;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Placing an order books its stock and numbers inside one transaction. The payment gateway is a remote
 * call that can take seconds; holding those row locks across it would stall every other checkout of the
 * same products, so the payment is started only once the placement is committed - and an order whose
 * payment cannot be started is cancelled, returning its stock.
 */
final class OrderPlacementTransactionTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        DecliningGatewayPaymentMethod::reset();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderPlacementTransactionTest/product_in_stock.csv');
    }

    #[Test]
    public function thePaymentIsStartedOutsideThePlacementTransaction(): void
    {
        $this->placeWithTheDecliningGateway();

        $this->assertFalse(DecliningGatewayPaymentMethod::$transactionActiveDuringInitiate);
    }

    #[Test]
    public function anOrderWhosePaymentCannotBeStartedIsCancelled(): void
    {
        $this->placeWithTheDecliningGateway();

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('status', 'payment_status')
            ->from('tx_products_domain_model_order')
            ->executeQuery()
            ->fetchAssociative();
        $this->assertSame(['status' => 'cancelled', 'payment_status' => 'failed'], $row);
    }

    #[Test]
    public function anOrderWhosePaymentCannotBeStartedReturnsItsStock(): void
    {
        $this->placeWithTheDecliningGateway();

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll();
        $stock = $queryBuilder->select('in_stock')
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        $this->assertSame(10, (int)$stock);
    }

    private function placeWithTheDecliningGateway(): void
    {
        $subject = $this->get(OrderPlacementTransaction::class);
        try {
            $subject->run(
                $this->request(),
                $this->basketViewModel(),
                new CheckoutSelections(),
                new Address(email: 'buyer@example.com', country: 'DE'),
                $this->get(PaymentMethodRegistry::class)->get('fixture-declining')
            );
            $this->fail('Expected the declined payment to abort the placement.');
        } catch (PaymentFailedException) {
            // expected: the gateway refused to start the payment
        }
    }

    private function request(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();

        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }

    private function basketViewModel(): BasketViewModel
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        $unitPriceNet = Money::fromDecimalString('84.03');
        $unitPriceGross = Money::fromDecimalString('100.00');
        $item = new BasketViewItem(
            $product,
            null,
            2,
            $unitPriceNet,
            $unitPriceGross,
            0.19,
            $unitPriceNet,
            $unitPriceGross,
            $unitPriceGross->subtract($unitPriceNet)
        );

        return new BasketViewModel([$item], $unitPriceNet, $unitPriceGross, $unitPriceGross->subtract($unitPriceNet), 'EUR');
    }
}
