<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A status mail tells the customer in words what happened to the order, and only when the order confirmation
 * does not already say it.
 */
final class OrderStatusMailTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderMailContentTest/complete_order.csv');
    }

    #[Test]
    public function theStatusMailNamesTheStatusesInWords(): void
    {
        $this->get(EventDispatcherInterface::class)->dispatch(new OrderStatusChangedEvent(1, OrderStatus::CONFIRMED, OrderStatus::SHIPPED));

        $email = TestMailer::getSentEmails()[0] ?? null;
        $this->assertNotNull($email);
        $this->assertStringContainsString('from "Confirmed" to "Shipped"', (string)$email->getTextBody());
        $this->assertStringNotContainsString('"confirmed"', (string)$email->getHtmlBody());
    }

    #[Test]
    public function confirmingAPlacedOrderSendsNoStatusMail(): void
    {
        $this->get(EventDispatcherInterface::class)->dispatch(new OrderStatusChangedEvent(1, OrderStatus::PENDING, OrderStatus::CONFIRMED));

        $this->assertSame([], TestMailer::getSentEmails());
    }
}
