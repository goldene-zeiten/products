<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Rating;

use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;
use GoldeneZeiten\Products\Shipping\DhlExpress\Domain\Dto\DhlExpressRate;
use GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressRateRequestEvent;
use GoldeneZeiten\Products\Shipping\DhlExpress\Exception\DhlExpressRatingException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(DhlExpressRatingClient::class)]
final class HttpDhlExpressRatingClient implements DhlExpressRatingClient
{
    private const RATES_PATH = '/rates';

    /**
     * The DHL price variant to charge the customer: the billing-currency figure, not the base or
     * public-rates figure DHL also returns.
     */
    private const BILLING_CURRENCY_TYPE = 'BILLC';

    public function __construct(
        private readonly ApiHttpClient $httpClient,
        private readonly DhlExpressRateRequestBuilder $requestBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return DhlExpressRate[]
     */
    public function rate(ShippingContext $context, DhlExpressConfiguration $configuration): array
    {
        $parameters = $this->buildParameters($context, $configuration);
        $url = $configuration->baseUrl() . self::RATES_PATH . '?' . http_build_query($parameters);
        try {
            $response = $this->httpClient->get($url, ['Authorization' => $configuration->authorizationHeader()]);
        } catch (ApiTransportException $exception) {
            throw new DhlExpressRatingException('DHL rate request failed at transport level.', 1752600800, $exception);
        }

        return $this->extractRates($response);
    }

    /**
     * @return array<string, string>
     */
    private function buildParameters(ShippingContext $context, DhlExpressConfiguration $configuration): array
    {
        $event = new ModifyDhlExpressRateRequestEvent(
            $this->requestBuilder->build($context, $configuration),
            $context,
            $configuration,
        );
        $this->eventDispatcher->dispatch($event);

        return $event->getParameters();
    }

    /**
     * @return DhlExpressRate[]
     */
    private function extractRates(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $data = json_decode((string)$response->getBody(), true);
        if ($status === 400) {
            // DHL reports an unserviceable lane as a 400 - a business empty result, not a failure.
            $this->logger->info('DHL returned no products for the shipment.');
            return [];
        }
        if ($status !== 200 || !is_array($data)) {
            throw new DhlExpressRatingException(sprintf('DHL rating returned HTTP %d.', $status), 1752600801);
        }

        return $this->mapRates($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return DhlExpressRate[]
     */
    private function mapRates(array $data): array
    {
        $products = $data['products'] ?? [];
        if (isset($products['productCode'])) {
            // A single product may come back as an object rather than a one-element list.
            $products = [$products];
        }

        return array_values(array_filter(array_map($this->toRate(...), is_array($products) ? $products : [])));
    }

    private function toRate(mixed $product): ?DhlExpressRate
    {
        if (!is_array($product)) {
            return null;
        }
        $productCode = (string)($product['productCode'] ?? '');
        $price = $this->billingPrice($product['totalPrice'] ?? []);
        if ($productCode === '' || $price === null) {
            return null;
        }

        return new DhlExpressRate(
            $productCode,
            (string)($product['productName'] ?? ''),
            $price['amount'],
            $price['currency'],
            (string)($product['deliveryCapabilities']['estimatedDeliveryDateAndTime'] ?? ''),
        );
    }

    /**
     * @param mixed $totalPrice
     * @return array{amount: string, currency: string}|null
     */
    private function billingPrice(mixed $totalPrice): ?array
    {
        if (!is_array($totalPrice)) {
            return null;
        }
        $fallback = null;
        foreach ($totalPrice as $price) {
            if (!is_array($price) || !isset($price['price'])) {
                continue;
            }
            $entry = ['amount' => (string)$price['price'], 'currency' => (string)($price['priceCurrency'] ?? '')];
            $fallback ??= $entry;
            if (($price['currencyType'] ?? '') === self::BILLING_CURRENCY_TYPE) {
                return $entry;
            }
        }

        return $fallback;
    }
}
