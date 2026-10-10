<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Tests\Functional\Payment;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Stripe\Amount\StripeAmountConverter;
use GoldeneZeiten\Products\Payment\Stripe\Client\StripeClientFactory;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfigurationFactory;
use GoldeneZeiten\Products\Payment\Stripe\Payment\StripePaymentMethod;
use GoldeneZeiten\Products\Payment\Stripe\Tests\Functional\AbstractStripeMockTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Site\Entity\Site;

final class StripePaymentMethodTest extends AbstractStripeMockTestCase
{
    private const RETRIEVE_PATH = '/payment/stripe/v1/checkout/sessions/cs_test_1';
    private const CREATE_PATH = '/payment/stripe/v1/checkout/sessions';

    #[Test]
    public function initiateCreatesASessionAndRequiresRedirect(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context());

        $this->assertSame(PaymentResultState::REDIRECT_REQUIRED, $result->getState());
        $this->assertSame('cs_test_1', $result->getExternalId());
        $this->assertStringContainsString('checkout.stripe.com', $result->getRedirectUrl());
    }

    /**
     * The shop keeps amounts in hundredths; Stripe wants the currency's own minor unit.
     */
    #[Test]
    #[DataProvider('minorUnitProvider')]
    public function initiateSendsTheAmountInTheCurrencysMinorUnit(string $currency, string $amount, string $expectedUnitAmount): void
    {
        $context = new PaymentContext(Money::fromDecimalString($amount), $currency, 'DE', 0, 'https://shop.example/return', 'https://shop.example/cancel', 'https://shop.example/webhook');

        $this->subject()->initiate($this->order(), $context);

        parse_str((string)$this->loggedRequests(self::CREATE_PATH)[0]['body'], $body);
        $this->assertSame($expectedUnitAmount, $body['line_items'][0]['price_data']['unit_amount']);
    }

    public static function minorUnitProvider(): \Generator
    {
        yield 'a two-decimal currency in cents' => ['currency' => 'EUR', 'amount' => '12.34', 'expectedUnitAmount' => '1234'];
        yield 'a zero-decimal currency in whole units' => ['currency' => 'JPY', 'amount' => '1000', 'expectedUnitAmount' => '1000'];
        yield 'a three-decimal currency in thousandths' => ['currency' => 'KWD', 'amount' => '12.34', 'expectedUnitAmount' => '12340'];
    }

    #[Test]
    #[DataProvider('minorUnitProvider')]
    public function handleWebhookAcceptsASessionPaidInTheCurrencysMinorUnit(string $currency, string $amount, string $expectedUnitAmount): void
    {
        $order = $this->order();
        $order->setCurrency($currency);
        $order->setTotalGross(Money::fromDecimalString($amount));

        $result = $this->subject()->handleWebhook($this->webhookRequest(true, amountTotal: (int)$expectedUnitAmount, currency: strtolower($currency)), $order);

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
    }

    #[Test]
    public function initiateFailsWithoutAConfiguredCheckoutPage(): void
    {
        $result = $this->subject()->initiate($this->order(), $this->context('', ''));

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleReturnConfirmsAPaidSession(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_1'), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('pi_test_1', $result->getExternalId());
    }

    #[Test]
    public function handleReturnOnAnAlreadyPaidOrderDoesNotCallStripe(): void
    {
        $order = $this->order();
        $order->setPaymentStatus(PaymentStatus::PAID);

        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_1'), $order);

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame(0, $this->recordedRequests(self::RETRIEVE_PATH, 'GET'), 'A paid order is not re-confirmed.');
    }

    #[Test]
    public function handleReturnFailsForAnUnpaidSession(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_unpaid'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    /**
     * Asynchronous methods (SEPA debit, bank transfers) complete the checkout before the money arrives; the
     * order is accepted with the payment pending rather than cancelled as unpaid.
     */
    #[Test]
    public function handleReturnReportsACompletedSessionAwaitingAnAsyncPaymentAsPending(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_async'), $this->order());

        $this->assertSame(PaymentResultState::PENDING, $result->getState());
        $this->assertSame('pi_test_async', $result->getExternalId());
    }

    #[Test]
    public function handleReturnDoesNotLeakTheStripeErrorMessageToTheCustomer(): void
    {
        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_error'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
        $this->assertStringNotContainsString('No such checkout.session', $result->getFailureReason(), 'The raw gateway message must not reach the shopper.');
    }

    #[Test]
    public function handleReturnRejectsAPaidSessionBoundToADifferentOrder(): void
    {
        $order = $this->order();
        $order->_setProperty('uid', 999);

        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_1'), $order);

        $this->assertSame(PaymentResultState::FAILED, $result->getState(), 'A paid session for order 42 must not settle order 999.');
    }

    #[Test]
    public function handleReturnRejectsAPaidSessionWithATamperedAmount(): void
    {
        $order = $this->order();
        $order->setTotalGross(Money::fromCents(999999));

        $result = $this->subject()->handleReturn($this->returnRequest('cs_test_1'), $order);

        $this->assertSame(PaymentResultState::FAILED, $result->getState(), 'A session paying less than the order total must not settle it.');
    }

    #[Test]
    public function handleReturnFailsWithoutASessionId(): void
    {
        $result = $this->subject()->handleReturn(new ServerRequest('https://shop.example/return', 'GET'), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleWebhookMarksPaidOnAVerifiedCompletedSession(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest(true), $this->order());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('pi_test_1', $result->getExternalId());
    }

    #[Test]
    public function handleWebhookRejectsAnInvalidSignature(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest(false), $this->order());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }

    #[Test]
    public function handleStaticWebhookResolvesTheOrderFromTheSessionAndMarksItPaid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->webhookRequest(true));

        $this->assertInstanceOf(Order::class, $resolution->getOrder());
        $this->assertSame(42, $resolution->getOrder()->getUid());
        $this->assertSame(PaymentStatus::PAID, $resolution->getResult()->getPaymentStatus());
    }

    #[Test]
    public function handleStaticWebhookReportsACompletedSessionAwaitingAnAsyncPaymentAsPending(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->webhookRequest(true, 'checkout.session.completed', 'unpaid'));

        $this->assertSame(42, $resolution->getOrder()?->getUid());
        $this->assertSame(PaymentResultState::PENDING, $resolution->getResult()->getState());
    }

    #[Test]
    public function handleStaticWebhookMarksPaidOnceAnAsyncPaymentSucceeds(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->webhookRequest(true, 'checkout.session.async_payment_succeeded'));

        $this->assertSame(42, $resolution->getOrder()?->getUid());
        $this->assertSame(PaymentStatus::PAID, $resolution->getResult()->getPaymentStatus());
    }

    #[Test]
    public function handleStaticWebhookFailsWhenAnAsyncPaymentFails(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->webhookRequest(true, 'checkout.session.async_payment_failed', 'unpaid'));

        $this->assertSame(42, $resolution->getOrder()?->getUid());
        $this->assertSame(PaymentResultState::FAILED, $resolution->getResult()->getState());
    }

    #[Test]
    public function handleStaticWebhookAcknowledgesAnEventTypeItDoesNotHandle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->webhookRequest(true, 'payment_intent.created'));

        $this->assertTrue($resolution->isIgnored());
    }

    #[Test]
    public function handleWebhookLeavesThePaymentUnchangedForAnEventTypeItDoesNotHandle(): void
    {
        $result = $this->subject()->handleWebhook($this->webhookRequest(true, 'payment_intent.created'), $this->order());

        $this->assertSame(PaymentResultState::UNCHANGED, $result->getState());
    }

    #[Test]
    public function handleStaticWebhookRejectsAnInvalidSignature(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');

        $resolution = $this->subject()->handleStaticWebhook($this->webhookRequest(false));

        $this->assertNull($resolution->getOrder(), 'An unverified webhook must not resolve an order.');
    }

    /**
     * Gateway callbacks run as middlewares before the page is resolved, where core has not published the
     * request globally yet. Credentials configured only on the site must still be found - from the site of
     * the callback request itself.
     */
    #[Test]
    public function handleStaticWebhookUsesTheCredentialsOfTheRequestsSite(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_for_webhook.csv');
        $subject = new StripePaymentMethod(
            new StripeConfigurationFactory($this->siteOnlyConfiguration(), new CurrentSiteResolver()),
            new StripeClientFactory(),
            $this->get(\Psr\EventDispatcher\EventDispatcherInterface::class),
            $this->get(OrderRepository::class),
            new StripeAmountConverter(),
            new NullLogger(),
        );

        $resolution = $subject->handleStaticWebhook($this->siteOnlyRequest($this->webhookRequest(true)));

        $this->assertSame(42, $resolution->getOrder()?->getUid());
        $this->assertSame(PaymentStatus::PAID, $resolution->getResult()->getPaymentStatus());
    }

    private function siteOnlyRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        $site = new Site('shop', 1, [
            'base' => 'https://shop.example/',
            'settings' => [
                'products' => [
                    'payment' => [
                        'stripe' => [
                            'secretKey' => 'sk_test_x',
                            'webhookSecret' => 'whsec_test',
                            'apiBaseUrl' => $this->mockRoot . '/payment/stripe',
                        ],
                    ],
                ],
            ],
        ]);

        return $request->withAttribute('site', $site);
    }

    private function siteOnlyConfiguration(): ApiSettingsResolver
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([]);

        return new ApiSettingsResolver($extensionConfiguration);
    }

    private function subject(): StripePaymentMethod
    {
        return new StripePaymentMethod(
            $this->configurationFactory(),
            new StripeClientFactory(),
            $this->get(\Psr\EventDispatcher\EventDispatcherInterface::class),
            $this->get(OrderRepository::class),
            new StripeAmountConverter(),
            new NullLogger(),
        );
    }

    private function configurationFactory(): StripeConfigurationFactory
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'secretKey' => 'sk_test_x',
            'webhookSecret' => 'whsec_test',
            'apiBaseUrl' => $this->mockRoot . '/payment/stripe',
        ]);

        return new StripeConfigurationFactory(new ApiSettingsResolver($extensionConfiguration), new CurrentSiteResolver());
    }

    private function returnRequest(string $sessionId): ServerRequestInterface
    {
        return (new ServerRequest('https://shop.example/return', 'GET'))->withQueryParams(['session_id' => $sessionId]);
    }

    private function webhookRequest(
        bool $validSignature,
        string $type = 'checkout.session.completed',
        string $paymentStatus = 'paid',
        int $amountTotal = 1234,
        string $currency = 'eur'
    ): ServerRequestInterface {
        $payload = json_encode(
            [
                'id' => 'evt_1',
                'object' => 'event',
                'type' => $type,
                'data' => [
                    'object' => [
                        'id' => 'cs_test_1',
                        'object' => 'checkout.session',
                        'status' => 'complete',
                        'payment_status' => $paymentStatus,
                        'payment_intent' => 'pi_test_1',
                        'client_reference_id' => '42',
                        'amount_total' => $amountTotal,
                        'currency' => $currency,
                    ],
                ],
            ],
            JSON_THROW_ON_ERROR,
        );
        $timestamp = time();
        $signature = $validSignature
            ? 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_test')
            : 't=' . $timestamp . ',v1=deadbeef';

        return new ServerRequest('https://shop.example/webhook', 'POST', $this->stream($payload), [
            'Stripe-Signature' => $signature,
        ]);
    }

    private function stream(string $body): Stream
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return $stream;
    }

    private function order(): Order
    {
        $order = new Order();
        $order->_setProperty('uid', 42);
        $order->setOrderNumber('ORD-1');
        $order->setCurrency('EUR');
        $order->setTotalGross(Money::fromCents(1234));

        return $order;
    }

    private function context(string $returnUrl = 'https://shop.example/return', string $cancelUrl = 'https://shop.example/cancel'): PaymentContext
    {
        return new PaymentContext(Money::fromDecimalString('12.34'), 'EUR', 'DE', 0, $returnUrl, $cancelUrl, 'https://shop.example/webhook');
    }
}
