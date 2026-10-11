<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\InvoicePaymentMethod;
use GoldeneZeiten\Products\Core\Payment\RefundablePaymentMethodInterface;
use GoldeneZeiten\Products\Core\Tests\Functional\OrderDataTestFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

final class InvoicePaymentMethodTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function implementsTheRefundableInterface(): void
    {
        $subject = $this->get(InvoicePaymentMethod::class);

        $this->assertInstanceOf(RefundablePaymentMethodInterface::class, $subject);
    }

    #[Test]
    public function refundCompletesWithTheRefundedPaymentStatus(): void
    {
        $subject = $this->get(InvoicePaymentMethod::class);
        $order = OrderDataTestFactory::minimal(invoiceNumber: 'INV-1');

        $result = $subject->refund($order, Money::fromCents(1999));

        $this->assertSame(PaymentResultState::COMPLETED, $result->getState());
        $this->assertSame(PaymentStatus::REFUNDED, $result->getPaymentStatus());
        $this->assertSame('INV-1', $result->getExternalId());
    }

    #[Test]
    public function cancelCompletesWithTheFailedPaymentStatus(): void
    {
        $subject = $this->get(InvoicePaymentMethod::class);
        $order = OrderDataTestFactory::minimal(invoiceNumber: 'INV-2');

        $result = $subject->cancel($order);

        $this->assertSame(PaymentResultState::COMPLETED, $result->getState());
        $this->assertSame(PaymentStatus::FAILED, $result->getPaymentStatus());
    }

    #[Test]
    public function isAvailableByDefault(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $this->assertTrue($this->get(InvoicePaymentMethod::class)->isAvailable($this->context()));
    }

    #[Test]
    public function isUnavailableWhenTheSiteDisablesIt(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))->withAttribute(
            'site',
            new Site('products', 1, ['settings' => ['products' => ['payment' => ['invoice' => ['enabled' => false]]]]])
        );

        $this->assertFalse($this->get(InvoicePaymentMethod::class)->isAvailable($this->context()));
    }

    private function context(): PaymentContext
    {
        return new PaymentContext(Money::fromCents(1999), 'EUR', 'DE', 0, '', '', '');
    }
}
