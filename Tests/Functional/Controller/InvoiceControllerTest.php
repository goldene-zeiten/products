<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Invoice\Exception\InvalidInvoiceTokenException;
use GoldeneZeiten\Products\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class InvoiceControllerTest extends AbstractFrontendTestCase
{
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_with_items_and_addresses.csv');
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        self::assertInstanceOf(Order::class, $order);
        $this->order = $order;
    }

    #[Test]
    public function downloadActionReturnsThePdfForAValidToken(): void
    {
        $token = $this->get(InvoiceTokenService::class)->generateToken($this->order);
        $response = $this->executeFrontendSubRequest($this->requestFor($token));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('%PDF', (string)$response->getBody());
    }

    #[Test]
    public function downloadActionRejectsATamperedToken(): void
    {
        $this->expectException(InvalidInvoiceTokenException::class);
        $this->expectExceptionCode(1752000001);

        $this->executeFrontendSubRequest($this->requestFor('not-a-valid-token'));
    }

    private function requestFor(string $hash): InternalRequest
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            sprintf('&id=2&tx_products_invoice[action]=download&tx_products_invoice[order]=%d&tx_products_invoice[hash]=%s', $this->order->getUid(), $hash)
        );

        return (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters([
                'type' => 1729512001,
                'tx_products_invoice[action]' => 'download',
                'tx_products_invoice[order]' => $this->order->getUid(),
                'tx_products_invoice[hash]' => $hash,
                'cHash' => $cHash,
            ]);
    }
}
