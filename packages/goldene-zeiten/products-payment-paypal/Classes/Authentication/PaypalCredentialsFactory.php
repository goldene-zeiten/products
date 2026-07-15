<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Authentication;

use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2Credentials;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration;

/**
 * Turns a resolved PayPal configuration into the OAuth 2.0 credentials the shared token provider needs.
 * Kept in one place so the order client and the webhook verifier build them the same way.
 */
final class PaypalCredentialsFactory
{
    private const TOKEN_PATH = '/v1/oauth2/token';

    public function forConfiguration(PaypalConfiguration $configuration): OAuth2Credentials
    {
        return new OAuth2Credentials(
            $configuration->baseUrl() . self::TOKEN_PATH,
            $configuration->clientId,
            $configuration->clientSecret,
        );
    }
}
