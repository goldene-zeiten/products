<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Event;

use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\EventFixture\OrderTrackingListener;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class OrderTrackingEventDispatchTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/orders_with_frontend_user.csv');
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/OrderControllerTest/guest_order.csv');
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/OrderControllerTest/order_history_content.csv');
        OrderTrackingListener::$enabled = false;
        OrderTrackingListener::$invocationCount = 0;
    }

    #[Test]
    public function orderTrackingEventIsDispatchedAndLinksRender(): void
    {
        OrderTrackingListener::$enabled = true;

        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(2);
        $this->assertNotNull($order);
        $hash = $this->get(OrderTokenService::class)->generateToken($order);

        $request = $this->orderShowRequest(2, $hash);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(1, OrderTrackingListener::$invocationCount);
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Track my parcel', $body);
        $this->assertStringContainsString('https://carrier.example/track/EVT-1', $body);
    }

    private function orderShowRequest(int $order, ?string $hash): InternalRequest
    {
        $queryParameters = [
            'tx_productscore_orderhistory[action]' => 'show',
            'tx_productscore_orderhistory[order]' => $order,
        ];
        if ($hash !== null) {
            $queryParameters['tx_productscore_orderhistory[hash]'] = $hash;
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
