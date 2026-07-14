<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Invoice\Exception\InvalidInvoiceTokenException;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class InvoiceControllerTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_with_items_and_addresses.csv');
    }

    #[Test]
    public function downloadActionReturnsThePdfForAValidToken(): void
    {
        $order = $this->fetchOrder();
        $token = $this->get(InvoiceTokenService::class)->generateToken($order);
        $response = $this->executeFrontendSubRequest($this->requestFor($order, $token));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string)$response->getBody());
    }

    #[Test]
    public function downloadActionRejectsATamperedToken(): void
    {
        $this->expectException(InvalidInvoiceTokenException::class);
        $this->expectExceptionCode(1752000001);

        $this->executeFrontendSubRequest($this->requestFor($this->fetchOrder(), 'not-a-valid-token'));
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    private function requestFor(Order $order, string $hash): InternalRequest
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            sprintf('&id=2&tx_productscore_invoice[action]=download&tx_productscore_invoice[order]=%d&tx_productscore_invoice[hash]=%s', $order->getUid(), $hash)
        );

        return (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters([
                'type' => 1729512001,
                'tx_productscore_invoice[action]' => 'download',
                'tx_productscore_invoice[order]' => $order->getUid(),
                'tx_productscore_invoice[hash]' => $hash,
                'cHash' => $cHash,
            ]);
    }
}
