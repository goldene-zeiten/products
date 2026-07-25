<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Middleware;

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

    private function webhookRequest(string $token, int $orderUid): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost' . PaymentUrlFactory::WEBHOOK_PATH, 'POST'))
            ->withQueryParams(['order' => (string)$orderUid, 'signature' => $token]);
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
