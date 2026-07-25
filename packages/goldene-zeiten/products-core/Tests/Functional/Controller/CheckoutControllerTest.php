<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
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
            '&id=2&tx_productscore_checkout[action]=payment'
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters([
                'tx_productscore_checkout[action]' => 'payment',
                'cHash' => $cHash,
            ]);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('method-invoice', (string)$response->getBody());
    }

    /**
     * cHash is a cache-integrity token, not an authorization secret: it is deterministic and TYPO3 appends
     * it to every generated link, so an attacker crafting the URL by hand computes the same value. A valid
     * cHash therefore does not stand in for owning the order - only the order token does.
     */
    #[Test]
    public function thankYouActionDoesNotLeakAnUnownedOrderWithoutAValidHash(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/orders_with_frontend_user.csv');

        $response = $this->executeFrontendSubRequest($this->thankYouRequest(1, null));

        $this->assertStringNotContainsString('ORD-1', (string)$response->getBody(), 'thankYou must not render an order the caller cannot prove it placed.');
    }

    #[Test]
    public function thankYouActionRendersTheOrderForAValidHash(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/orders_with_frontend_user.csv');

        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertNotNull($order);
        $hash = $this->get(OrderTokenService::class)->generateToken($order);

        $response = $this->executeFrontendSubRequest($this->thankYouRequest(1, $hash));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('ORD-1', (string)$response->getBody());
    }

    private function thankYouRequest(int $order, ?string $hash): InternalRequest
    {
        $queryParameters = [
            'tx_productscore_checkout[action]' => 'thankYou',
            'tx_productscore_checkout[order]' => $order,
        ];
        if ($hash !== null) {
            $queryParameters['tx_productscore_checkout[hash]'] = $hash;
        }

        $parameterString = '&id=2';
        foreach ($queryParameters as $key => $value) {
            $parameterString .= '&' . $key . '=' . $value;
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($parameterString);

        return (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters(array_merge($queryParameters, ['cHash' => $cHash]));
    }
}
