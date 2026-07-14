..  include:: /Includes.rst.txt
..  _developer-api:

=============
Provider APIs
=============

Some extension points in the Products extension use **interface-based contracts** rather than
events. These APIs define explicit implementations that must be registered by integrators — they
are not optional listeners but mandatory service implementations for specific integration tasks.

**Key Difference: Contracts vs Events**

-   **Events** (PSR-14) let listeners react to shop lifecycle milestones, mutate lists, or log
    activity after the fact. Many listeners may respond; each is independent.
-   **Provider APIs** (interfaces) describe a service the shop depends on. The controller or backend
    asks the service a specific question (e.g., "which payment methods are available?") and expects
    a deterministic answer. These APIs carry registration metadata — typically a Symfony
    :php:`#[AutoconfigureTag]` attribute — so an integrator only needs to implement the interface;
    no manual configuration is required.

The Shared Pattern
==================

All provider APIs follow the same architecture:

1. **The Interface** carries a :php:`#[AutoconfigureTag('products.*')]` attribute.
   Any class implementing it is automatically registered by the dependency-injection container —
   no :file:`Services.yaml` entry needed (as long as your extension has :code:`autoconfigure: true`).

2. **The Registry** collects all implementations via :php:`#[TaggedIterator]` and provides
   methods to query them: :php:`has()`, :php:`get()`, :php:`getAvailable()`, etc.

3. **The Context DTO** is an immutable, read-only value object passed to your provider's methods.
   It carries everything the provider may decide on (the request, the order, the basket, etc.) —
   so the provider never reads the HTTP request or session directly.

4. **Events are Filters, Not Registration** — A PSR-14 event (e.g., :php:`PaymentMethodsCollectedEvent`)
   is dispatched **after** discovery is complete. Listeners can reorder or hide already-collected
   providers, but the event is **not** how you register a provider. Registration is via the interface.

Two Cross-Cutting Rules
=======================

**Providers Are Stateless**
    Your provider is instantiated once per container lifetime. Read everything you need from the
    context DTO passed to your methods, never from the constructor. Do not cache decisions or
    product lists in your instance variables. If the logged-in customer changes, the locale
    changes, or the shop configuration changes, your provider must see the new state — which comes
    from the context, not from cached instance state.

**Labels Are Denormalized in Orders**
    When a customer places an order using a provider (e.g., a discount, a shipping method, a
    product listing), the order records both the provider's identifier **and** a human-readable
    label. The label is a snapshot of what the provider returned at order time. This ensures the
    order displays correctly even if the provider's addon is later uninstalled or its label
    changes. You supply the label when the provider is discovered or selected; the order stores
    it permanently.

APIs by Purpose
===============

**Money & Totals**

..  toctree::
    :maxdepth: 1
    :titlesonly:

    CheckoutAdjustments

:ref:`Checkout Adjustments API <developer-api-checkout-adjustments>`
    Define how to add charges, discounts, fees, and taxes to an order total.

**Checkout Providers**

..  toctree::
    :maxdepth: 1
    :titlesonly:

    PaymentMethods
    ShippingProviders
    Discounts
    Loyalty

:ref:`Payment Methods API <developer-api-payment-methods>`
    Implement payment gateways for credit cards, wallets, invoices, or custom processors.

:ref:`Shipping Providers API <developer-api-shipping-providers>`
    Implement carrier integrations for DHL, FedEx, regional couriers, or custom logistics.

:ref:`Discounts API <developer-api-discounts>`
    Implement price reductions for vouchers, promotional codes, loyalty redemptions, or custom
    business rules.

:ref:`Loyalty API <developer-api-loyalty>`
    Implement a programme a customer both spends on an order and earns from it - points, cashback,
    tiered rewards.

**Catalogue & Fulfilment**

..  toctree::
    :maxdepth: 1
    :titlesonly:

    ProductListModes
    OrderExport

:ref:`Product List Modes API <developer-api-product-list-modes>`
    Implement custom product listings — "staff picks", "clearance", "products you can afford"
    — placeable in a content element by editors.

:ref:`Order Export API <developer-api-order-export>`
    Implement order export formats for ERP systems, fulfillment partners, accounting software,
    or analytics platforms.
