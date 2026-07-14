..  include:: /Includes.rst.txt
..  _developer-api-product-list-modes:

=======================
Product List Modes API
=======================

The Product List Modes API enables integrators to implement custom product listings — "products you
can afford with your points", "staff picks", "clearance", or any view the editor may place in a
product-list content element. A list mode is more than a query: it is a view the editor selects
in the backend, so a provider supplies both the products **and** the label the choice shows.
Because product listing is inherently shop-specific, **the extension ships with only basic listings**
(all, offers, highlights, new); integrators add custom views by implementing the interface.

**Location:** :php:`GoldeneZeiten\Products\Catalog\ProductListModeProviderInterface`

A Listing Is a View, Not Just a Query
======================================

Many product-filtering APIs expose a query builder or a search method. This API is different:
a **list mode is a content-element choice**, placed by an editor like "show all products" or
"show special offers". The editor must see what mode they are choosing, so the mode supplies
both:

-   **The Label** — What the editor sees in the content-element dropdown
    (e.g., :code:`'Products You Can Afford'`, :code:`'Staff Favorites'`).
-   **The Products** — What the frontend renders when that mode is selected.

Built-In Listings Stay Built In
================================

The extension's own listings (all, offers, highlights, new, articles) are hardcoded in the
content-element plugin and do not use this API. This is the seam for integrators to add more
without touching core code.

The bundled "affordable" listing (credit-points feature) itself is implemented as a registered
list mode, so you can see the pattern in action: it supplies a label and filters products by
whether the logged-in customer can afford them with their points.

Registration
============

A class implementing :php:`ProductListModeProviderInterface` is automatically registered — no
manual entry in :file:`Configuration/Services.yaml` is required. The interface itself carries the
:php:`#[AutoconfigureTag('products.product_list_mode')]` attribute, so Symfony's autowiring
discovers and collects all implementations.

Interface Contract
==================

..  code-block:: php

    interface ProductListModeProviderInterface
    {
        public function getMode(): string;
        public function getLabel(): string;
        public function findProducts(ProductListContext $context): array;
    }

**Methods:**

:php:`getMode(): string`
    The stored value that selects this mode — what the content element records and the controller
    dispatches on (e.g., :code:`'affordable'`, :code:`'staff_picks'`, :code:`'clearance'`).
    This is the identifier used in the order or cache.

:php:`getLabel(): string`
    The human-readable label the editor sees when choosing this listing in the content element
    (e.g., :code:`'Products You Can Afford'`, :code:`'Staff Favorites'`). Shown in a dropdown
    with other registered modes and the built-in options.

:php:`findProducts(ProductListContext $context): Product[]`
    Compute and return the products for this listing. The context carries the HTTP request, so
    the provider can read what it needs (logged-in user, site settings, etc.) from there. Return
    an array of :php:`Product` objects; an empty array if no products match.

ProductListContext
===================

An immutable, read-only value object passed to :php:`findProducts()`. It carries:

:php:`getRequest(): ServerRequestInterface`
    The current HTTP request. The provider uses this to resolve whatever it depends on
    (the logged-in customer, locale, site configuration, etc.). The provider never reads
    the session or configuration directly; it reads the request instead, which carries the
    contextual state it needs.

Backend Integration: The Items Provider
========================================

When an editor opens a product-list content element, the backend asks **which modes are available**.
This is handled by :php:`ProductListModeItemsProvider`, which is an :code:`itemsProcFunc` that
appends registered modes to the element's dropdown. Your provider is discovered automatically
and appears alongside the built-in options ("All", "Offers", "Highlights", etc.).

**Editor Experience:**

1. Editor opens a product-list content element in the backend.
2. Backend populates the :code:`list_mode` field with built-in options.
3. :php:`ProductListModeItemsProvider::populate()` appends all registered modes.
4. Editor sees "All | Offers | Highlights | New | Articles | **Staff Picks | Clearance | …**"
5. Editor selects one. The element stores the mode identifier (e.g., :code:`'staff_picks'`).

**Frontend Experience:**

1. Controller resolves the stored mode via :php:`ProductListModeRegistry::findProducts(mode)`.
2. If a mode is registered, the registry calls your provider's :php:`findProducts()`.
3. If a mode is not registered (addon was uninstalled), the controller falls back to built-in.
4. Template renders the returned products.

Statelessness and Context
==========================

Your provider must be stateless. Do not cache the product list or decision logic in the provider
itself. Do not read the HTTP request in the constructor; take explicit context parameters instead.

**Why:** Providers are instantiated once per dependency-injection container lifetime. A customer
logging in, or the shop locale changing, must not be visible as stale cached state in your
provider instance.

**Instead:** Read everything you need from the :php:`ProductListContext`. The context is immutable
and carries the request, which is the source of truth for the current user, locale, and settings.

Order Storage and Denormalization
==================================

When an order is placed, the list mode used to fetch products is recorded in two fields:

:php:`Order.product_listing_mode`
    The mode identifier (e.g., :code:`'affordable'`, :code:`'staff_picks'`). Used by the order
    confirmation and email templates to show which listing generated the order.

:php:`Order.product_listing_label`
    The human-readable label (e.g., :code:`'Products You Can Afford'`). **Intentionally
    denormalized** — the order must display correctly even if the addon that registered the
    mode is later uninstalled. Without the denormalized label, an uninstalled addon would render
    as an unknown identifier.

Example: Staff Picks Provider
==============================

This example implements a simple staff picks listing managed in the backend:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Catalog;

    use GoldeneZeiten\Products\Catalog\ProductListModeProviderInterface;
    use GoldeneZeiten\Products\Domain\Dto\Catalog\ProductListContext;
    use GoldeneZeiten\Products\Domain\Repository\ProductRepository;

    /**
     * A curated list of staff-picked products.
     */
    final class StaffPicksListModeProvider implements ProductListModeProviderInterface
    {
        public function __construct(
            private readonly ProductRepository $productRepository
        ) {}

        public function getMode(): string
        {
            return 'staff_picks';
        }

        public function getLabel(): string
        {
            return 'Staff Favorites';
        }

        /**
         * @return \GoldeneZeiten\Products\Domain\Model\Product[]
         */
        public function findProducts(ProductListContext $context): array
        {
            // Example: products tagged with "staff_pick" category (uid 42)
            $staffPickCategory = 42;
            return $this->productRepository->findByCategory(
                $this->productRepository->findCategoryByUid($staffPickCategory)
            );
        }
    }

Register it in your extension's :file:`Configuration/Services.yaml` (or rely on autoconfiguration):

..  code-block:: yaml

    services:
      _defaults:
        autowire: true
        autoconfigure: true

      MyVendor\MyExtension\Catalog\StaffPicksListModeProvider: ~

The provider will be discovered automatically, and the editor will see "Staff Favorites" as an
option in the content element.

Example: Customer-Specific Clearance Listing
=============================================

This example shows a more sophisticated provider that reads the request to make customer-specific
decisions:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Catalog;

    use GoldeneZeiten\Products\Catalog\ProductListModeProviderInterface;
    use GoldeneZeiten\Products\Domain\Dto\Catalog\ProductListContext;
    use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
    use TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository;

    /**
     * Products on clearance, with customer group restrictions.
     */
    final class ClearanceListModeProvider implements ProductListModeProviderInterface
    {
        public function __construct(
            private readonly ProductRepository $productRepository,
            private readonly FrontendUserRepository $frontendUserRepository
        ) {}

        public function getMode(): string
        {
            return 'clearance';
        }

        public function getLabel(): string
        {
            return 'Clearance Sale';
        }

        /**
         * @return \GoldeneZeiten\Products\Domain\Model\Product[]
         */
        public function findProducts(ProductListContext $context): array
        {
            $request = $context->getRequest();

            // Example: only show clearance to logged-in customers
            // (Customer groups, permissions, etc. could be read from the user object)
            $frontendUser = $request->getAttribute('frontend.user');
            if (!$frontendUser || !$frontendUser->user['uid'] ?? 0) {
                return []; // Guest: show nothing
            }

            // Find products marked as clearance (e.g., all products on page 99)
            // In reality, you'd use a custom query or category.
            return $this->productRepository->findAllIgnoringStoragePage();
        }
    }

The Registry
============

The :php:`ProductListModeRegistry` collects all registered providers and makes them available
to the controller and backend form:

:php:`has(string $mode): bool`
    Check whether a mode is registered.

:php:`findProducts(string $mode, ProductListContext $context): Product[]`
    Resolve a mode and fetch its products. Returns an empty array if the mode is not registered.

:php:`getSelectItems(): array<array{label: string, value: string}>`
    Return all registered modes as backend select items (used by the content element dropdown).
    Each item has a :code:`label` (what the editor sees) and a :code:`value` (the mode identifier).
