<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class CheckoutControllerTest extends AbstractFrontendTestCase
{
    #[Test]
    public function paymentActionListsInvoicePaymentMethod(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');

        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_products_checkout[action]=payment'
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters([
                'tx_products_checkout[action]' => 'payment',
                'cHash' => $cHash,
            ]);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('method-invoice', (string)$response->getBody());
    }
}
