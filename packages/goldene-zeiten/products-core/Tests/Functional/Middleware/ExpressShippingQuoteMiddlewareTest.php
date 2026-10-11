<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Middleware;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressBasket;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\ExpressBasketTokenService;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Express\ExpressOrderService;
use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * The express shipping-rate callback answers the wallet sheet directly - no session, the basket proven by
 * its signed token and the destination taken from the request body. The fixture carrier serves "FX", so a
 * real request through the middleware produces a real quote.
 */
final class ExpressShippingQuoteMiddlewareTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    #[Test]
    public function aSignedBasketAndDestinationYieldAShippingQuote(): void
    {
        $response = $this->get(ExpressShippingQuoteMiddleware::class)->process(
            $this->request(['basketToken' => $this->basketToken(), 'country' => 'FX', 'postalCode' => '12345']),
            $this->failingHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame('EUR', $payload['currency']);
        $this->assertCount(1, $payload['options']);
        $this->assertSame('fixture-shipping:standard', $payload['options'][0]['key']);
        $this->assertSame(500, $payload['options'][0]['shippingAmount']);
        $this->assertSame(10500, $payload['options'][0]['orderTotal']);
    }

    #[Test]
    public function aForgedBasketTokenIsRejected(): void
    {
        $response = $this->get(ExpressShippingQuoteMiddleware::class)->process(
            $this->request(['basketToken' => 'forged.token', 'country' => 'FX', 'postalCode' => '12345']),
            $this->failingHandler()
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function anUnrelatedPathIsHandedOn(): void
    {
        $response = $this->get(ExpressShippingQuoteMiddleware::class)->process(
            (new ServerRequest('http://localhost/some/page', 'GET'))->withAttribute('site', $this->site()),
            $this->passThroughHandler()
        );

        $this->assertInstanceOf(HtmlResponse::class, $response);
        $this->assertSame('PASSED-THROUGH', (string)$response->getBody());
    }

    /**
     * The sheet total must be what the order charges: goods, the carrier, and the fees the core quote adds
     * (here a 3.00 handling fee), not goods plus carrier alone.
     */
    #[Test]
    public function theQuotedOrderTotalIsTheTotalTheCoreQuoteCharges(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Express/Fixtures/express_with_fees.csv');
        $request = $this->requestWithBasket(['basketToken' => $this->basketToken(), 'provider' => 'fixture-express', 'country' => 'DE', 'postalCode' => '10115']);

        $response = $this->get(ExpressShippingQuoteMiddleware::class)->process($request, $this->failingHandler());

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertCount(1, $payload['options']);
        $this->assertSame('tablerate:1', $payload['options'][0]['key']);
        $this->assertSame(500, $payload['options'][0]['shippingAmount']);
        $this->assertSame(10800, $payload['options'][0]['orderTotal']);
    }

    #[Test]
    public function theQuotedOrderTotalEqualsWhatThePlacedOrderWouldCharge(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Express/Fixtures/express_with_fees.csv');
        $request = $this->requestWithBasket(['basketToken' => $this->basketToken(), 'provider' => 'fixture-express', 'country' => 'DE', 'postalCode' => '10115']);
        $basket = $this->get(BasketService::class)->getBasketViewModel($request, 'DE');

        $response = $this->get(ExpressShippingQuoteMiddleware::class)->process($request, $this->failingHandler());

        $quoted = $this->get(ExpressOrderService::class)->quote(
            $request,
            $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
            $basket,
            new Address(country: 'DE', zip: '10115'),
            'tablerate:1'
        );
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame($quoted->getTotalGross()->getCents(), $payload['options'][0]['orderTotal']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requestWithBasket(array $body): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $request = $this->request($body)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $this->feesSite());
        $this->get(BasketService::class)->add($request, 1, null, 1);

        return $request;
    }

    private function feesSite(): Site
    {
        return new Site('products', 1, [
            'base' => 'http://localhost/',
            'rootPageId' => 1,
            'settings' => ['products' => ['shipping' => ['enabled' => true], 'handling' => ['enabled' => true]]],
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);
    }

    private function basketToken(): string
    {
        return $this->get(ExpressBasketTokenService::class)->issue(new ExpressBasket(
            [new ShippingContextItem(1, 1000, false, 'standard')],
            1000,
            Money::fromCents(10000),
            'EUR',
            0
        ));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): ServerRequestInterface
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write(json_encode($body, JSON_THROW_ON_ERROR));
        $stream->rewind();

        return (new ServerRequest('http://localhost' . ExpressShippingQuoteMiddleware::PATH, 'POST', $stream))
            ->withAttribute('site', $this->site());
    }

    private function site(): Site
    {
        return new Site('products', 1, [
            'base' => 'http://localhost/',
            'rootPageId' => 1,
            'settings' => ['products' => ['shipping' => ['enabled' => true]]],
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);
    }

    private function failingHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('The express callback must answer itself, not delegate.', 1784220768);
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
}
