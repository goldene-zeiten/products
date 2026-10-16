<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\InvoicePaymentMethod;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * A shop may refuse invoice for some deliveries, e.g. when the order is collected and paid in store.
 */
final class InvoiceShippingExclusionTest extends AbstractFunctionalTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    #[DataProvider('shippingProvider')]
    public function invoiceIsOfferedUnlessTheChosenShippingIsExcluded(string $shippingOptionKey, bool $expectedAvailable): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['payment' => ['invoice' => ['excludedShipping' => 'pickup, ups:express']]]]]);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
        $context = new PaymentContext(Money::fromCents(10000), 'EUR', 'DE', shippingOptionKey: $shippingOptionKey);

        $this->assertSame($expectedAvailable, $this->get(InvoicePaymentMethod::class)->isAvailable($context));
    }

    public static function shippingProvider(): \Generator
    {
        yield 'an excluded provider' => [
            'shippingOptionKey' => 'pickup:3',
            'expectedAvailable' => false,
        ];
        yield 'an excluded option' => [
            'shippingOptionKey' => 'ups:express',
            'expectedAvailable' => false,
        ];
        yield 'another option of that provider' => [
            'shippingOptionKey' => 'ups:standard',
            'expectedAvailable' => true,
        ];
        yield 'another provider' => [
            'shippingOptionKey' => 'tablerate:1',
            'expectedAvailable' => true,
        ];
        yield 'no shipping chosen yet' => [
            'shippingOptionKey' => '',
            'expectedAvailable' => true,
        ];
    }
}
