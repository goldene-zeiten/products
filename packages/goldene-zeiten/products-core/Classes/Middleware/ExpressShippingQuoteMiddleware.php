<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Middleware;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressCheckoutProviderNotFoundException;
use GoldeneZeiten\Products\Core\Express\ExpressBasketTokenService;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Express\ExpressShippingQuoteService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The live shipping-rate callback a wallet sheet (Apple Pay / Google Pay / Stripe Express) hits when the
 * buyer picks or changes their address, before an order exists.
 *
 * It is a fixed-path middleware, not a plugin action, on purpose: the wallet calls it directly, without the
 * plugin/render machinery, and it must answer fast. It runs after the site is resolved and the frontend user
 * authenticated but before page resolution, since its path is not a page. The basket the shipping options are
 * computed for is proven by its signed token, and the destination arrives street-redacted in the body -
 * exactly what a shipping quote needs. When the request names its express provider, each option also carries
 * the order total the customer's session basket would be charged ({@see ExpressShippingQuoteService::withOrderTotals()}).
 * The response is a wallet-agnostic quote; each express provider's own JS reshapes it for its sheet.
 */
final readonly class ExpressShippingQuoteMiddleware implements MiddlewareInterface
{
    public const PATH = '/products/express/shipping-quote';

    public function __construct(
        private ExpressBasketTokenService $basketTokenService,
        private ExpressShippingQuoteService $shippingQuoteService,
        private ProductsConfigurationFactory $configurationFactory,
        private ExpressCheckoutProviderRegistry $providerRegistry
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if ($request->getUri()->getPath() !== self::PATH || !$site instanceof Site) {
            return $handler->handle($request);
        }

        $body = json_decode((string)$request->getBody(), true);
        $body = is_array($body) ? $body : [];

        $basket = $this->basketTokenService->resolve($this->stringOrNull($body['basketToken'] ?? null));
        if ($basket === null) {
            return new JsonResponse(['error' => 'invalid_basket_token'], 400);
        }

        $address = new Address(
            country: (string)($body['country'] ?? ''),
            zip: (string)($body['postalCode'] ?? ''),
            state: (string)($body['state'] ?? '')
        );
        $quote = $this->shippingQuoteService->quote($basket, $address, $this->configurationFactory->createFromSite($site), $site);
        $providerIdentifier = $this->stringOrNull($body['provider'] ?? null);
        if ($providerIdentifier === null) {
            return new JsonResponse($quote->toArray());
        }

        try {
            $provider = $this->providerRegistry->get($providerIdentifier);
        } catch (ExpressCheckoutProviderNotFoundException) {
            return new JsonResponse(['error' => 'unknown_provider'], 400);
        }

        return new JsonResponse($this->shippingQuoteService->withOrderTotals($quote, $request, $provider, $address)->toArray());
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
