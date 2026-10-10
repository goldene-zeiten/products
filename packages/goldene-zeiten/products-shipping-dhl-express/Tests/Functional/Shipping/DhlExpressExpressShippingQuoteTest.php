<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Functional\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressBasket;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\ExpressBasketTokenService;
use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Functional\AbstractDhlExpressMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The express shipping-rate callback runs as a middleware before the page is resolved, so core has not
 * published the request globally yet. DHL Express credentials configured only on the site must still be found -
 * from the site of the callback request itself.
 */
final class DhlExpressExpressShippingQuoteTest extends AbstractDhlExpressMockTestCase
{
    #[Test]
    public function siteOnlyCredentialsQuoteDhlExpressOnTheExpressShippingCallback(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_shipping_dhl_express'] = [];

        $response = $this->get(ExpressShippingQuoteMiddleware::class)->process(
            $this->request(['basketToken' => $this->basketToken(), 'country' => 'BE', 'postalCode' => '1000']),
            $this->failingHandler()
        );

        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame(['dhl:P', 'dhl:U'], array_column($payload['options'], 'key'));
    }

    private function basketToken(): string
    {
        return $this->get(ExpressBasketTokenService::class)->issue(new ExpressBasket(
            [new ShippingContextItem(1, 2500, false, '')],
            2500,
            Money::fromCents(5000),
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
            'settings' => [
                'products' => [
                    'shipping' => [
                        'enabled' => true,
                        'dhlexpress' => [
                            'environment' => 'sandbox',
                            'accountNumber' => 'ACC123',
                            'username' => 'site-user',
                            'password' => 'site-pass',
                            'originCountryCode' => 'DE',
                            'originPostCode' => '53113',
                            'originCityName' => 'Bonn',
                            'weightUnit' => 'metric',
                            'apiBaseUrl' => $this->mockRoot . '/shipping/dhl-express',
                        ],
                    ],
                ],
            ],
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
                throw new \LogicException('The express callback must answer itself, not delegate.', 1791666652);
            }
        };
    }
}
