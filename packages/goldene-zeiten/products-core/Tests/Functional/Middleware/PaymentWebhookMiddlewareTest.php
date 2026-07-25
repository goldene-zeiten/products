<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Middleware;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Middleware\PaymentWebhookMiddleware;
use GoldeneZeiten\Products\Core\Payment\PaymentUrlFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * The webhook endpoint is posted to by the gateway, not a browser. A forged or unknown callback must be
 * refused without confirming which order uids exist - the rejection is an integrity check, not a lookup an
 * outsider can probe.
 */
final class PaymentWebhookMiddlewareTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    #[Test]
    public function aForgedWebhookIsRejectedWithoutDisclosingTheProbedOrderUid(): void
    {
        $response = $this->get(PaymentWebhookMiddleware::class)->process(
            $this->webhookRequest('forged-token', 4711),
            $this->failingHandler()
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('4711', (string)$response->getBody(), 'A rejected webhook must not echo the order uid it was probed with.');
    }

    #[Test]
    public function aStaticWebhookResolvesTheOrderFromThePayloadAndFinalizesIt(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Payment/Fixtures/pages_for_url_test.csv');
        $this->importCSVDataSet(__DIR__ . '/../Payment/Fixtures/orders_for_callback_test.csv');

        $response = $this->get(PaymentWebhookMiddleware::class)->process(
            $this->staticWebhookRequest('fixture-static-webhook', 3),
            $this->failingHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
        $order = $this->get(OrderRepository::class)->findByUid(3);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
    }

    #[Test]
    public function aStaticWebhookForAnUnregisteredGatewayIsRejected(): void
    {
        $response = $this->get(PaymentWebhookMiddleware::class)->process(
            $this->staticWebhookRequest('no-such-gateway', 3),
            $this->failingHandler()
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    private function webhookRequest(string $token, int $orderUid): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost' . PaymentUrlFactory::WEBHOOK_PATH, 'POST'))
            ->withQueryParams(['order' => (string)$orderUid, 'signature' => $token]);
    }

    private function staticWebhookRequest(string $gateway, int $orderUid): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost' . PaymentUrlFactory::staticWebhookPath($gateway), 'POST'))
            ->withQueryParams(['order' => (string)$orderUid]);
    }

    private function failingHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new HtmlResponse('PASSED-THROUGH');
            }
        };
    }
}
