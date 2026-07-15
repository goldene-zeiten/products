:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-layering:

Extension configuration and site settings
=============================================

Configuration is **layered**, resolved by the shared
:php:`GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`: system-wide defaults live in the
extension configuration, and any of them can be overridden per site. A non-empty site setting overrides the
extension-configuration default; an **empty** site setting inherits it. This lets one installation carry a
global default while a multi-shop instance runs different credentials, or a different origin address, per
site.

*   **Extension configuration** — :guilabel:`Admin Tools > Settings > Extension Configuration >
    products_shipping_dhl_express`. The system-wide defaults.
*   **Site settings** — activate the :guilabel:`Products DHL Express Shipping` site set, then adjust its
    settings under :guilabel:`Site Management > Sites > Edit settings`, category :guilabel:`Shipping &
    Handling > DHL Express`. The keys are ``products.shipping.dhlexpress.*``.

:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfigurationFactory` is the only
place that reads either source; it builds the immutable
:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration` value object every
other class in the extension consumes, so no service resolves settings or the request itself.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.shipping.dhlexpress.environment
        :type: string (sandbox | production)
        :Default: sandbox

        Which DHL Express (MyDHL API) environment to call. ``sandbox`` uses DHL's test environment
        (``express.api.dhl.com/mydhlapi/test``); ``production`` uses the live host
        (``express.api.dhl.com/mydhlapi``). Sandbox credentials only work against the sandbox environment,
        and vice versa.

    ..  confval:: products.shipping.dhlexpress.accountNumber
        :type: string
        :Default: (empty)

        Your DHL Express account number. Normally required for DHL to return rates for your account, but
        not required for
        :php:`GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration::isComplete()`
        to hold.

    ..  confval:: products.shipping.dhlexpress.username
        :type: string
        :Default: (empty)

        Your MyDHL API key (used as the HTTP Basic username), from a DHL developer app at
        `developer.dhl.com <https://developer.dhl.com>`__.

    ..  confval:: products.shipping.dhlexpress.password
        :type: string
        :Default: (empty)

        Your MyDHL API secret (used as the HTTP Basic password). Keep it out of version control — store it
        in the extension configuration (Install Tool storage) or reference an environment variable from the
        site setting.

    ..  confval:: products.shipping.dhlexpress.originCountryCode
        :type: string
        :Default: DE

        ISO 3166-1 alpha-2 country the shipment is sent from. Also used to decide whether a shipment is
        customs-declarable: the request marks it declarable whenever the destination country differs from
        this origin country.

    ..  confval:: products.shipping.dhlexpress.originPostCode
        :type: string
        :Default: (empty)

        Postal code the shipment is sent from. Not required for the configuration to be considered
        complete, but DHL rates more accurately with it set.

    ..  confval:: products.shipping.dhlexpress.originCityName
        :type: string
        :Default: (empty)

        City the shipment is sent from. Unlike UPS, DHL Express *requires* an origin city for a rate
        request; together with `products.shipping.dhlexpress.username`,
        `products.shipping.dhlexpress.password` and `products.shipping.dhlexpress.originCountryCode`, this
        is required for
        :php:`DhlExpressConfiguration::isComplete()` to hold — without it, the carrier stays silent and the
        table-rate fallback serves every basket.

    ..  confval:: products.shipping.dhlexpress.usedProducts
        :type: string
        :Default: (empty)

        A comma-separated allow-list of DHL product codes to offer, e.g. ``P,U,K``. Leave empty to offer
        every product DHL returns. See
        :php:`GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration::offersProduct()`.

    ..  confval:: products.shipping.dhlexpress.weightUnit
        :type: string (metric | imperial)
        :Default: metric

        The unit the basket weight and package dimensions are sent to DHL in — kilograms/centimetres or
        pounds/inches. Any value other than ``imperial`` falls back to ``metric``.

    ..  confval:: products.shipping.dhlexpress.apiBaseUrl
        :type: string
        :Default: (empty)

        Advanced. Sends the DHL Express rate calls to this host instead of the environment's real DHL host
        — for a proxy, or a local mock server used in tests. Leave empty to use the environment default
        (:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressEnvironment::baseUrl()`).

..  _configuration-destination-and-dimensions:

Destination city and package dimensions
==========================================

..  note::

    The shop only tracks a delivery **country and postcode** for the basket, but DHL Express's rate
    request also wants a destination **city**. The extension sends the postcode as the destination city as
    well as the postcode field — DHL geocodes a rate request by postcode and country regardless of what the
    city name says, so this does not affect the quoted rate. Likewise, no per-basket package dimensions
    exist in the shop, so a small-parcel default (20 × 15 × 10, in the configured unit) is sent for every
    request.

    An integrator who needs a real destination city, or real package dimensions, adjusts the outgoing
    request in a listener on
    :php:`GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressRateRequestEvent` — see
    :ref:`Developer <developer-modify-rate-request>`.

..  _configuration-how-rating-behaves:

How rating behaves
====================

At checkout the customer sees live DHL Express products and prices, pooled into one list together with any
other carrier's options (including the shop's own table-rate methods). DHL Express is a real carrier, so it
supersedes the table-rate methods whenever it returns at least one option for the basket. If DHL is
unconfigured (any of the username, password, origin country or origin city is empty), unreachable, returns
an error, or has no rate for that shipment, it offers nothing and the table-rate methods serve the basket
instead — the customer is never left without a shipping option. See
:ref:`Relationship to table-rate shipping <introduction-table-rate-fallback>`.

..  _configuration-troubleshooting:

Troubleshooting
==================

If no DHL Express options appear at checkout:

*   Check `products.shipping.dhlexpress.username` and `products.shipping.dhlexpress.password`, and that
    `products.shipping.dhlexpress.environment` matches the credentials (sandbox credentials only work
    against the sandbox environment).
*   Check `products.shipping.dhlexpress.originCountryCode` and `products.shipping.dhlexpress.originCityName`
    are set — without both (and the credentials), the configuration is incomplete and the carrier never
    calls DHL at all.
*   Check the basket ships to a country and postcode DHL Express actually serves from that origin.
*   Check the core shop's own shipping-cost setting (EXT:products_core) is enabled — without it, no carrier,
    DHL Express included, is asked for options.

The extension logs an error whenever a DHL call genuinely fails (unreachable, transport fault, unexpected
response), so a site's PHP log is the next place to look if the above does not explain it. An incomplete
configuration or a DHL "no products for this lane" answer (HTTP 400) logs nothing beyond an info-level
note — both are an expected empty result, not a failure.
