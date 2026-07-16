<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Signing;

use Amazon\Pay\API\Client as AmazonPayApiClient;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfiguration;

/**
 * Produces the signed headers Amazon Pay requires on every API request (RSASSA-PSS over a canonical
 * request). The signing math is fiddly and security-critical, so it is reused unmodified from the official
 * `amzn/amazon-pay-api-sdk-php` rather than reimplemented - only its signing is used, not its curl
 * transport, so the calls still go over the shared PSR-18 client and stay mockable.
 */
final class AmazonPaySigner
{
    /**
     * The signature covers the exact JSON body that is sent, so the caller must serialize the body the
     * same way {@see ApiHttpClient::postJson()} does - both use `json_encode` with default flags on the
     * same array, so the bytes match. The idempotency key is signed but intentionally not returned by the
     * SDK, so it is added back here for the wire.
     *
     * @param array<string, mixed> $payload the request body ([] for a GET)
     * @return array<string, string> header name => value
     */
    public function signedHeaders(
        string $method,
        string $url,
        array $payload,
        AmazonPayConfiguration $configuration,
        ?string $idempotencyKey = null
    ): array {
        $client = new AmazonPayApiClient([
            'public_key_id' => $configuration->publicKeyId,
            'private_key' => $configuration->privateKey,
            'region' => $configuration->region->value,
            'sandbox' => $configuration->sandbox,
            'algorithm' => 'AMZN-PAY-RSASSA-PSS-V2',
        ]);

        $payloadJson = $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
        $preSigned = $idempotencyKey !== null ? ['x-amz-pay-idempotency-key' => $idempotencyKey] : null;

        $headers = [];
        foreach ($client->getPostSignedHeaders($method, $url, [], $payloadJson, $preSigned) as $line) {
            [$name, $value] = explode(':', $line, 2);
            $headers[$name] = $value;
        }
        if ($idempotencyKey !== null) {
            $headers['x-amz-pay-idempotency-key'] = $idempotencyKey;
        }

        return $headers;
    }
}
