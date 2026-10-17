<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Invoice;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceRenderer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * An invoice has to add up and to name who issued it (§ 14 (4) UStG): every charge and reduction of the order,
 * the seller with address and VAT ID or tax number, and when the goods were supplied.
 */
final class InvoiceContentTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/InvoiceContentTest/pickup_cash_order.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'withdrawal' => [
                            'trader' => [
                                'name' => 'Goldene Zeiten Juweliere GmbH',
                                'address' => "Neupfarrplatz 1\n93047 Regensburg",
                            ],
                        ],
                        'invoice' => [
                            'vatId' => 'DE811234567',
                            'taxNumber' => '244/123/45678',
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    #[DataProvider('contentProvider')]
    public function theInvoiceCarriesWhatTheLawRequires(string $expectedContent): void
    {
        $html = $this->get(InvoiceRenderer::class)->render($this->fetchOrder());

        $this->assertStringContainsString($expectedContent, $html);
    }

    public static function contentProvider(): \Generator
    {
        yield 'goods total' => ['expectedContent' => '€2,500.80'];
        yield 'discount' => ['expectedContent' => '-€75.02'];
        yield 'total' => ['expectedContent' => '€2,425.78'];
        yield 'VAT of the rate' => ['expectedContent' => '€387.31'];
        yield 'shipping by its label' => ['expectedContent' => 'Pick-up at Regensburg (Neupfarrplatz 1, 93047 Regensburg)'];
        yield 'seller name' => ['expectedContent' => 'Goldene Zeiten Juweliere GmbH'];
        yield 'seller address' => ['expectedContent' => '93047 Regensburg'];
        yield 'VAT ID' => ['expectedContent' => 'DE811234567'];
        yield 'tax number' => ['expectedContent' => '244/123/45678'];
        yield 'date of supply' => ['expectedContent' => 'Date of supply'];
    }

    #[Test]
    public function theTotalsAddUpOnce(): void
    {
        $html = $this->get(InvoiceRenderer::class)->render($this->fetchOrder());

        $this->assertSame(1, substr_count($html, '€2,425.78'), 'The total is printed once, not followed by a second shipping row.');
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
