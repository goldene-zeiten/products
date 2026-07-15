:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

=========
Developer
=========

This library exposes three services. All three are autowired and can simply be constructor-injected
— except :php:`OAuth2ClientCredentialsProvider`, which needs one extra step described in
:ref:`developer-oauth2-wiring`.

..  contents:: Table of contents
    :local:

..  _developer-http-client:

ApiHttpClient
=============

**Location:** :php:`GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient`

A thin wrapper around the PSR-18 :php:`Psr\Http\Client\ClientInterface` that removes the
request-building boilerplate every integration would otherwise repeat: create a stream, wrap it in a
:php:`TYPO3\CMS\Core\Http\Request`, send it, and turn a transport-level failure into a single
exception type. It deliberately returns the raw :php:`Psr\Http\Message\ResponseInterface` and never
inspects the status code — mapping an HTTP status to business meaning (a 400 that really means "no
rate available", a 422 that is a declined capture) stays with the caller.

**Methods:**

..  code-block:: php

    public function postJson(string $url, array $body, array $headers = []): ResponseInterface
    public function postForm(string $url, string $body, array $headers = []): ResponseInterface
    public function post(string $url, array $headers = []): ResponseInterface
    public function get(string $url, array $headers = []): ResponseInterface

*   :php:`postJson()` — JSON-encodes ``$body`` and sets ``Content-Type: application/json``.
*   :php:`postForm()` — sends ``$body`` as an already-encoded ``application/x-www-form-urlencoded``
    string (used by the OAuth2 provider itself for the ``grant_type=client_credentials`` body).
*   :php:`post()` — a POST with no body at all, for endpoints (e.g. capturing an already-described
    order) that take their whole input from the URL.
*   :php:`get()` — a plain GET.

Every call sets ``Accept: application/json`` in addition to ``$headers``. A transport-level failure
(connection reset, DNS failure, timeout — anything below the HTTP layer, so no response was ever
received) is caught and rethrown as
:php:`GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException`.

**Example:**

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace Vendor\MyIntegration\Api;

    use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
    use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;

    final class HttpOrderClient
    {
        public function __construct(
            private readonly ApiHttpClient $httpClient,
        ) {}

        public function createOrder(string $baseUrl, array $payload, string $bearerToken): array
        {
            try {
                $response = $this->httpClient->postJson(
                    $baseUrl . '/v2/checkout/orders',
                    $payload,
                    ['Authorization' => 'Bearer ' . $bearerToken],
                );
            } catch (ApiTransportException $exception) {
                throw new \RuntimeException('Order creation failed at transport level.', 0, $exception);
            }

            return json_decode((string)$response->getBody(), true) ?? [];
        }
    }

Inject :php:`ApiHttpClient` directly; it needs no extra wiring.

..  _developer-oauth2:

OAuth2ClientCredentialsProvider
================================

**Location:** :php:`GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider`

Performs the OAuth 2.0 client-credentials grant — HTTP Basic credentials plus a
``grant_type=client_credentials`` form body — against a token endpoint, and caches the resulting
bearer token until shortly before it expires (``expires_in`` from the token response, scaled by an
internal 0.8 safety factor, with a 60-second floor). A failure anywhere in the exchange (non-200
response, unparsable body, missing ``access_token``) throws
:php:`GoldeneZeiten\Products\ApiClient\Exception\OAuth2AuthenticationException`.

**Method:**

..  code-block:: php

    public function getToken(OAuth2Credentials $credentials, bool $forceRefresh = false): string

Call it with ``$forceRefresh = true`` to bypass the cache once — the standard reaction to a 401 from
the actual API call, since a gateway can revoke a token before its stated expiry:

..  code-block:: php

    $token = $this->tokenProvider->getToken($credentials);
    $response = $this->send($payload, $token);
    if ($response->getStatusCode() === 401) {
        $token = $this->tokenProvider->getToken($credentials, true);
        $response = $this->send($payload, $token);
    }

**Location:** :php:`GoldeneZeiten\Products\ApiClient\Authentication\OAuth2Credentials`

The per-call input to :php:`getToken()` — a plain, immutable value object carrying the *absolute*
token URL (it already encodes the environment, so the provider itself stays free of any per-vendor
knowledge), the client id and the client secret:

..  code-block:: php

    final readonly class OAuth2Credentials
    {
        public function __construct(
            public string $tokenUrl,
            public string $clientId,
            public string $clientSecret,
        ) {}
    }

    $credentials = new OAuth2Credentials(
        'https://onlinetools.ups.com/security/v1/oauth/token',
        $clientId,
        $clientSecret,
    );
    $token = $this->tokenProvider->getToken($credentials);

The token is cached under :php:`$credentials->cacheIdentifier()`, an MD5 of the token URL and the
client id — so a change of environment (sandbox vs. production, a different UPS/PayPal base URL) or
a change of client id never reuses a stale token from another configuration.

..  _developer-oauth2-wiring:

Wiring the provider with your own token cache
-----------------------------------------------

:php:`OAuth2ClientCredentialsProvider` is deliberately **excluded** from this package's own service
auto-registration:

..  code-block:: yaml
    :caption: packages/goldene-zeiten/products-api-client/Configuration/Services.yaml

    services:
      _defaults:
        autowire: true
        autoconfigure: true
        public: false

      GoldeneZeiten\Products\ApiClient\:
        resource: '../Classes/*'
        exclude:
          # Needs a token cache the consuming package owns, so each integration wires its own
          # instance (with its own cache) rather than sharing one.
          - '../Classes/Authentication/OAuth2ClientCredentialsProvider.php'
          - '../Classes/Exception/*'

The reason is the constructor's second argument:

..  code-block:: php

    public function __construct(
        private readonly ApiHttpClient $httpClient,
        private readonly FrontendInterface $tokenCache,
    ) {}

If the library auto-wired a cache for :php:`$tokenCache`, every integration installed side by side
would share **one** token store — a UPS token and a PayPal token colliding on the same cache
identifiers, or worse, one integration's ``forceRefresh`` evicting another's still-valid token. So
instead, each consuming package registers its own cache and its own named provider instance.

**1. Register the cache**, in the consumer's own ``ext_localconf.php``:

..  code-block:: php
    :caption: packages/goldene-zeiten/products-shipping-ups/ext_localconf.php

    use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
    use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

    defined('TYPO3') or die();

    (static function (): void {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['products_shipping_ups_token'] ??= [
            'frontend' => VariableFrontend::class,
            'backend' => Typo3DatabaseBackend::class,
            'groups' => [
                'system',
            ],
        ];
    })();

**2. Wire the cache and a named provider instance**, in the consumer's own
``Configuration/Services.yaml``:

..  code-block:: yaml
    :caption: packages/goldene-zeiten/products-shipping-ups/Configuration/Services.yaml

    services:
      _defaults:
        autowire: true
        autoconfigure: true
        public: false

      GoldeneZeiten\Products\Shipping\Ups\:
        resource: '../Classes/*'

      # TYPO3 only exposes cache.* DI services for its own built-in caches, so the extension's
      # token cache is defined here via a lazy CacheManager::getCache() factory.
      cache.products_shipping_ups_token:
        class: TYPO3\CMS\Core\Cache\Frontend\FrontendInterface
        factory:
          - '@TYPO3\CMS\Core\Cache\CacheManager'
          - 'getCache'
        arguments:
          - 'products_shipping_ups_token'

      # The shared OAuth token provider is instantiated with this extension's own token cache, so
      # UPS tokens never share storage with another integration's.
      products_shipping_ups.oauth_token_provider:
        class: GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider
        arguments:
          $tokenCache: '@cache.products_shipping_ups_token'

      GoldeneZeiten\Products\Shipping\Ups\Rating\HttpUpsRatingClient:
        arguments:
          $tokenProvider: '@products_shipping_ups.oauth_token_provider'

**3. Inject the named service** into whichever class needs it, by naming the argument explicitly —
autowiring cannot pick between two :php:`OAuth2ClientCredentialsProvider` instances on its own:

..  code-block:: php

    final class HttpUpsRatingClient implements UpsRatingClient
    {
        public function __construct(
            private readonly ApiHttpClient $httpClient,
            private readonly OAuth2ClientCredentialsProvider $tokenProvider,
            // ...
        ) {}
    }

``goldene-zeiten/products-payment-paypal`` repeats exactly this pattern under its own
``products_payment_paypal_token`` cache and ``products_payment_paypal.oauth_token_provider``
service id — every consumer follows the same three steps with its own extension key baked into the
cache and service identifiers.

..  _developer-configuration:

Layered configuration
======================

**Location:** :php:`GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`

Resolves a set of configuration values by layering a system-wide
:php:`TYPO3\CMS\Core\Configuration\ExtensionConfiguration` under a
:php:`TYPO3\CMS\Core\Site\Entity\Site`'s settings:

..  code-block:: php

    public function resolve(string $extensionKey, string $settingsPrefix, array $fields, ?Site $site): array

*   ``$extensionKey`` — the extension key to read ``ext_conf_template.txt`` defaults from.
*   ``$settingsPrefix`` — the site-settings key prefix, e.g. ``'products.shipping.ups.'``.
*   ``$fields`` — the configuration keys to resolve, without the prefix, e.g.
    ``['clientId', 'clientSecret', 'apiBaseUrl']``.
*   ``$site`` — the site whose settings may override the defaults, or :php:`null` if there is none
    (a CLI context, for instance) — in which case only the extension-configuration defaults apply.

**Layering rule:** for each field, a **non-empty** site setting
(``$settingsPrefix . $field``) overrides the extension-configuration default; an **empty** site
setting inherits the default. This lets a single installation carry one global default (set once in
the Extension Configuration backend module) while a multi-site instance overrides individual fields
— say, a different ``clientId``/``clientSecret`` — per site, without having to repeat every field on
every site.

**Location:** :php:`GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver`

..  code-block:: php

    public function resolve(): ?Site

Returns the site of the current frontend request (via the global ``TYPO3_REQUEST``'s ``site``
attribute, or :php:`null` if there is none), so a configuration factory can pick up the per-site
overrides for the request it is serving without threading the request through every caller that
needs configuration.

**Worked example — a configuration factory:**

The pattern every integration package follows: inject both resolvers into one small, ``final
readonly`` factory that is the *only* place that ever reads either settings source, and have it map
the resolved array onto the integration's own typed, immutable configuration value object.

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace Vendor\MyIntegration\Configuration;

    use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
    use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
    use TYPO3\CMS\Core\Site\Entity\Site;

    final readonly class MyIntegrationConfigurationFactory
    {
        private const EXTENSION_KEY = 'my_integration';

        private const SETTINGS_PREFIX = 'myintegration.';

        private const FIELDS = [
            'clientId',
            'clientSecret',
            'apiBaseUrl',
        ];

        public function __construct(
            private ApiSettingsResolver $settingsResolver,
            private CurrentSiteResolver $currentSiteResolver,
        ) {}

        public function forCurrentRequest(): MyIntegrationConfiguration
        {
            return $this->forSite($this->currentSiteResolver->resolve());
        }

        public function forSite(?Site $site): MyIntegrationConfiguration
        {
            $value = $this->settingsResolver->resolve(
                self::EXTENSION_KEY,
                self::SETTINGS_PREFIX,
                self::FIELDS,
                $site,
            );

            return new MyIntegrationConfiguration(
                clientId: $value['clientId'],
                clientSecret: $value['clientSecret'],
                apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
            );
        }
    }

Every consumer of ``MyIntegrationConfiguration`` afterwards stays free of both the settings source
and the request — it depends only on the value object. This mirrors
:php:`GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfigurationFactory` in
``goldene-zeiten/products-shipping-ups``, which resolves ``environment``, ``clientId``,
``clientSecret``, ``accountNumber`` and the origin-address fields the same way under the
``products.shipping.ups.`` settings prefix.

Exceptions
==========

..  confval:: ApiTransportException

    :php:`GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException` — thrown by
    :php:`ApiHttpClient` when a request fails below the HTTP layer (connection reset, DNS failure,
    timeout), so no response was ever received. A :php:`\RuntimeException`.

..  confval:: OAuth2AuthenticationException

    :php:`GoldeneZeiten\Products\ApiClient\Exception\OAuth2AuthenticationException` — thrown by
    :php:`OAuth2ClientCredentialsProvider` when a token cannot be obtained: the token endpoint
    returned a non-200 status, a body that was not valid JSON, or JSON with no ``access_token``. A
    :php:`\RuntimeException`.

Both are unchecked (:php:`\RuntimeException`) and deliberately generic — the caller turns either into
its own domain outcome (a shipping carrier stays silent and falls back to another method, a payment
method reports failure to the shopper).
