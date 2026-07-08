<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Invoice;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class InvoiceTokenServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private InvoiceTokenService $subject;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
        $this->subject = $this->get(InvoiceTokenService::class);
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        self::assertInstanceOf(Order::class, $order);
        $this->order = $order;
    }

    #[Test]
    public function aGeneratedTokenIsValidForTheSameOrder(): void
    {
        $token = $this->subject->generateToken($this->order);

        self::assertTrue($this->subject->isValid($this->order, $token));
    }

    #[Test]
    public function aTamperedTokenIsRejected(): void
    {
        $token = $this->subject->generateToken($this->order);

        self::assertFalse($this->subject->isValid($this->order, $token . 'x'));
    }

    #[Test]
    public function anEmptyTokenIsRejected(): void
    {
        self::assertFalse($this->subject->isValid($this->order, ''));
    }
}
