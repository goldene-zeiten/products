<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressAmountMismatchException;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressOrderFailedException;
use GoldeneZeiten\Products\Core\Shipping\Exception\MissingShippingOptionException;
use GoldeneZeiten\Products\Express\Stripe\Express\Exception\ExpressPaymentDeclinedException;
use GoldeneZeiten\Products\Express\Stripe\Express\StripeExpressConfirmService;
use GoldeneZeiten\Products\Express\Stripe\Tests\Functional\AbstractStripeExpressMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The confirm orchestration end to end: recompute the amount from the shop's basket, settle it over the
 * real Stripe SDK against the mock, and create the paid order - all through the real core services, so the
 * order recorded is a genuine one attributed to the express provider.
 */
final class StripeExpressConfirmServiceTest extends AbstractStripeExpressMockTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../../../products-core/Tests/Functional/Express/Fixtures/express_with_fees.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_express_stripe'] = [
            'publishableKey' => 'pk_test',
            'secretKey' => 'sk_test_x',
            'apiBaseUrl' => $this->mockRoot . '/express/stripe',
        ];
    }

    #[Test]
    public function aConfirmedWalletPaymentSettlesAndCreatesThePaidOrder(): void
    {
        $order = $this->get(StripeExpressConfirmService::class)->confirm(
            $this->request(),
            $this->basketViewModel($this->product()),
            new Address(email: 'wallet@example.com', firstName: 'Wallet', lastName: 'Buyer', street: 'Wallet St 1', zip: '10115', city: 'Berlin', country: 'DE'),
            '',
            'pm_card_visa'
        );

        $this->assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
        $this->assertSame('stripe-express', $order->getPaymentMethod());
        $this->assertNotSame('', $order->getOrderNumber());
    }

    #[Test]
    public function aDeclinedCardCreatesNoOrder(): void
    {
        $this->expectException(ExpressPaymentDeclinedException::class);
        $this->expectExceptionCode(1784220771);

        $this->get(StripeExpressConfirmService::class)->confirm(
            $this->request(),
            $this->basketViewModel($this->product()),
            new Address(country: 'DE'),
            '',
            'pm_declined'
        );
    }

    #[Test]
    public function theWalletIsChargedTheFullOrderTotal(): void
    {
        $order = $this->get(StripeExpressConfirmService::class)->confirm(
            $this->request(shipping: true),
            $this->basketViewModel($this->product()),
            new Address(email: 'wallet@example.com', firstName: 'Wallet', lastName: 'Buyer', street: 'Wallet St 1', zip: '10115', city: 'Berlin', country: 'DE'),
            'tablerate:1',
            'pm_card_visa'
        );

        // 100.00 goods + 5.00 shipping + 3.00 handling fee, not the 105.00 goods-plus-carrier figure.
        parse_str((string)$this->loggedRequests('/express/stripe/v1/payment_intents')[0]['body'], $body);
        $this->assertSame('10800', $body['amount']);
        $this->assertSame(10800, $order->getTotalGross()->getCents());
        $this->assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
    }

    #[Test]
    public function aMissingShippingOptionIsRejectedBeforeTheCardIsCharged(): void
    {
        try {
            $this->get(StripeExpressConfirmService::class)->confirm(
                $this->request(shipping: true),
                $this->basketViewModel($this->product()),
                new Address(email: 'wallet@example.com', firstName: 'Wallet', lastName: 'Buyer', street: 'Wallet St 1', zip: '10115', city: 'Berlin', country: 'DE'),
                '',
                'pm_card_visa'
            );
            $this->fail('Expected ExpressOrderFailedException was not thrown.');
        } catch (ExpressOrderFailedException $exception) {
            $this->assertInstanceOf(MissingShippingOptionException::class, $exception->getPrevious());
        }

        $this->assertSame([], $this->loggedRequests('/express/stripe/v1/payment_intents'));
    }

    #[Test]
    public function aPaymentThatSettledADifferentAmountIsRefunded(): void
    {
        try {
            $this->get(StripeExpressConfirmService::class)->confirm(
                $this->request(),
                $this->basketViewModel($this->product()),
                new Address(email: 'wallet@example.com', firstName: 'Wallet', lastName: 'Buyer', street: 'Wallet St 1', zip: '10115', city: 'Berlin', country: 'DE'),
                '',
                'pm_underpaid'
            );
            $this->fail('Expected ExpressAmountMismatchException was not thrown.');
        } catch (ExpressAmountMismatchException) {
            // expected
        }

        $refunds = $this->loggedRequests('/express/stripe/v1/refunds');
        $this->assertCount(1, $refunds);
        parse_str((string)$refunds[0]['body'], $body);
        $this->assertSame('pi_test_underpaid', $body['payment_intent']);
    }

    /**
     * Express callbacks may run before core publishes the current request, so credentials configured only
     * on the site must be read from the site of the request the service is handed.
     */
    #[Test]
    public function siteOnlyCredentialsSettleTheExpressPayment(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_express_stripe'] = [];
        $request = $this->request()->withAttribute('site', new Site('products', 1, [
            'base' => 'http://localhost/',
            'rootPageId' => 1,
            'settings' => [
                'products' => [
                    'express' => [
                        'stripe' => [
                            'publishableKey' => 'pk_test',
                            'secretKey' => 'sk_test_x',
                            'apiBaseUrl' => $this->mockRoot . '/express/stripe',
                        ],
                    ],
                ],
            ],
        ]));

        $order = $this->get(StripeExpressConfirmService::class)->confirm(
            $request,
            $this->basketViewModel($this->product()),
            new Address(email: 'wallet@example.com', firstName: 'Wallet', lastName: 'Buyer', street: 'Wallet St 1', zip: '10115', city: 'Berlin', country: 'DE'),
            '',
            'pm_card_visa'
        );

        $this->assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
    }

    private function request(bool $shipping = false): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => [
            'shipping' => ['enabled' => $shipping],
            'handling' => ['enabled' => $shipping],
        ]]]);

        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site);
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
}
