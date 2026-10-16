<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Offline\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Prepayment, cash on delivery and cash on pick-up are offered as the shop configures them and as the
 * chosen delivery allows: cash only when the order is collected, cash on delivery only when a carrier
 * brings it.
 */
final class OfflinePaymentMethodsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-offline',
    ];

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $paymentSettings
     */
    #[Test]
    #[DataProvider('availabilityProvider')]
    public function aMethodIsOfferedAsConfiguredAndAsTheDeliveryAllows(array $paymentSettings, string $shippingOptionKey, string $identifier, bool $expectedAvailable): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['payment' => $paymentSettings]]]);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
        $context = new PaymentContext(Money::fromCents(10000), 'EUR', 'DE', shippingOptionKey: $shippingOptionKey);

        $this->assertSame($expectedAvailable, $this->get(PaymentMethodRegistry::class)->get($identifier)->isAvailable($context));
    }

    public static function availabilityProvider(): \Generator
    {
        yield 'prepayment is off by default' => [
            'paymentSettings' => [],
            'shippingOptionKey' => 'tablerate:1',
            'identifier' => 'prepayment',
            'expectedAvailable' => false,
        ];
        yield 'prepayment needs an account to transfer to' => [
            'paymentSettings' => ['prepayment' => ['enabled' => true]],
            'shippingOptionKey' => 'tablerate:1',
            'identifier' => 'prepayment',
            'expectedAvailable' => false,
        ];
        yield 'prepayment with an account' => [
            'paymentSettings' => ['prepayment' => ['enabled' => true, 'iban' => 'DE02 1203 0000 0000 2020 51']],
            'shippingOptionKey' => 'tablerate:1',
            'identifier' => 'prepayment',
            'expectedAvailable' => true,
        ];
        yield 'cash on delivery with a carrier' => [
            'paymentSettings' => ['cashOnDelivery' => ['enabled' => true]],
            'shippingOptionKey' => 'tablerate:1',
            'identifier' => 'cashondelivery',
            'expectedAvailable' => true,
        ];
        yield 'cash on delivery without a carrier' => [
            'paymentSettings' => ['cashOnDelivery' => ['enabled' => true]],
            'shippingOptionKey' => 'pickup:2',
            'identifier' => 'cashondelivery',
            'expectedAvailable' => false,
        ];
        yield 'cash when collecting' => [
            'paymentSettings' => ['cash' => ['enabled' => true]],
            'shippingOptionKey' => 'pickup:2',
            'identifier' => 'cash',
            'expectedAvailable' => true,
        ];
        yield 'cash when shipped' => [
            'paymentSettings' => ['cash' => ['enabled' => true]],
            'shippingOptionKey' => 'tablerate:1',
            'identifier' => 'cash',
            'expectedAvailable' => false,
        ];
        yield 'cash with a configured collecting option' => [
            'paymentSettings' => ['cash' => ['enabled' => true, 'shipping' => 'tablerate:9']],
            'shippingOptionKey' => 'tablerate:9',
            'identifier' => 'cash',
            'expectedAvailable' => true,
        ];
    }

    /**
     * @param array<string, mixed> $paymentSettings
     */
    #[Test]
    #[DataProvider('feeProvider')]
    public function aMethodChargesItsFeeOrGrantsItsDiscount(array $paymentSettings, string $identifier, int $expectedFee): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['payment' => $paymentSettings]]]);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
        $context = new PaymentContext(Money::fromCents(12345), 'EUR', 'DE', shippingOptionKey: 'pickup:2');

        $this->assertSame($expectedFee, $this->get(PaymentMethodRegistry::class)->get($identifier)->calculateFee($context));
    }

    public static function feeProvider(): \Generator
    {
        yield 'cash on delivery fee' => [
            'paymentSettings' => ['cashOnDelivery' => ['fee' => '4.40']],
            'identifier' => 'cashondelivery',
            'expectedFee' => 440,
        ];
        yield 'cash discount' => [
            'paymentSettings' => ['cash' => ['discountPercent' => '3']],
            'identifier' => 'cash',
            'expectedFee' => -370,
        ];
        yield 'prepayment discount with a decimal comma' => [
            'paymentSettings' => ['prepayment' => ['discountPercent' => '2,5']],
            'identifier' => 'prepayment',
            'expectedFee' => -309,
        ];
        yield 'no discount configured' => [
            'paymentSettings' => [],
            'identifier' => 'cash',
            'expectedFee' => 0,
        ];
    }
}
