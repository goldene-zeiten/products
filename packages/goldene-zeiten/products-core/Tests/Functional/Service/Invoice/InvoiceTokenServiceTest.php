<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Invoice;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class InvoiceTokenServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
    }

    #[Test]
    #[DataProvider('tokenValidationProvider')]
    public function tokenValidationBehavior(bool $useGeneratedToken, string $tokenModifier, bool $shouldBeValid): void
    {
        $subject = $this->get(InvoiceTokenService::class);
        $order = $this->fetchOrder();
        $token = $useGeneratedToken ? $subject->generateToken($order) . $tokenModifier : $tokenModifier;

        $this->assertSame($shouldBeValid, $subject->isValid($order, $token));
    }

    public static function tokenValidationProvider(): \Generator
    {
        yield 'generated token is valid' => ['useGeneratedToken' => true, 'tokenModifier' => '', 'shouldBeValid' => true];
        yield 'tampered token is rejected' => ['useGeneratedToken' => true, 'tokenModifier' => 'x', 'shouldBeValid' => false];
        yield 'empty token is rejected' => ['useGeneratedToken' => false, 'tokenModifier' => '', 'shouldBeValid' => false];
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
