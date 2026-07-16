<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Middleware;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Middleware\PaymentReturnMiddleware;
use GoldeneZeiten\Products\Core\Payment\PaymentTokenService;
use GoldeneZeiten\Products\Core\Payment\PaymentUrlFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The payment return runs as a middleware, not a plugin action, because a gateway appends its own query
 * parameters to the URL it sends the customer back to - which a cHash-validated route rejects. What matters
 * here is that the middleware finalizes the order on a valid token and redirects to a clean checkout URL,
 * regardless of any extra parameters, and never finalizes on a forged one.
 */
final class PaymentReturnMiddlewareTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Payment/Fixtures/pages_for_url_test.csv');
        $this->importCSVDataSet(__DIR__ . '/../Payment/Fixtures/orders_for_callback_test.csv');
    }

    #[Test]
    public function returnWithValidTokenFinalizesTheOrderAndRedirectsToThankYou(): void
    {
        $response = $this->get(PaymentReturnMiddleware::class)->process(
            $this->request(PaymentUrlFactory::RETURN_PATH, $this->validToken(), ['session_id' => 'cs_gateway_appended']),
            $this->failingHandler()
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $location = urldecode($response->getHeaderLine('location'));
        $this->assertStringContainsString('/checkout', $location);
        $this->assertStringContainsString('thankYou', $location);
        $this->assertStringContainsString('[order]=1', $location);
        $this->assertSame(PaymentStatus::PAID, $this->fetchOrder(1)->getPaymentStatus());
    }

    #[Test]
    public function returnWithForgedTokenRedirectsBackToPaymentWithoutFinalizing(): void
    {
        $response = $this->get(PaymentReturnMiddleware::class)->process(
            $this->request(PaymentUrlFactory::RETURN_PATH, 'forged-token'),
            $this->failingHandler()
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('payment', urldecode($response->getHeaderLine('location')));
        $this->assertSame(PaymentStatus::OPEN, $this->fetchOrder(1)->getPaymentStatus());
    }

    #[Test]
    public function cancelLeavesTheOrderOpenAndRedirectsToPayment(): void
    {
        $response = $this->get(PaymentReturnMiddleware::class)->process(
            $this->request(PaymentUrlFactory::CANCEL_PATH, $this->validToken()),
            $this->failingHandler()
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('payment', urldecode($response->getHeaderLine('location')));
        $this->assertSame(PaymentStatus::OPEN, $this->fetchOrder(1)->getPaymentStatus());
    }

    #[Test]
    public function anUnrelatedPathIsHandedOnUntouched(): void
    {
        $response = $this->get(PaymentReturnMiddleware::class)->process(
            $this->request('/some/content/page', $this->validToken()),
            $this->passThroughHandler()
        );

        $this->assertInstanceOf(HtmlResponse::class, $response);
        $this->assertSame('PASSED-THROUGH', (string)$response->getBody());
    }

    private function validToken(): string
    {
        return $this->get(PaymentTokenService::class)->generateToken($this->fetchOrder(1));
    }

    /**
     * @param array<string, string> $extraQuery parameters a real gateway would append to the return URL
     */
    private function request(string $path, string $token, array $extraQuery = []): ServerRequestInterface
    {
        $site = new Site('products', 1, [
            'base' => 'http://localhost/',
            'rootPageId' => 1,
            'settings' => ['products' => ['pids' => ['checkoutPage' => 5]]],
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);

        return (new ServerRequest('http://localhost' . $path))
            ->withAttribute('site', $site)
            ->withQueryParams(['order' => '1', 'signature' => $token, ...$extraQuery]);
    }

    private function failingHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('The middleware must handle a payment callback itself, not delegate.', 1784073650);
            }
        };
    }

    private function passThroughHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new HtmlResponse('PASSED-THROUGH');
            }
        };
    }

    private function fetchOrder(int $uid): Order
    {
        $order = $this->get(OrderRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
