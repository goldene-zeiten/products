:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

=============================
Developer / Extension Points
=============================

How the voucher feature plugs into EXT:products (core)'s discount seam, and the extension points it
offers of its own.

..  contents:: Table of contents
    :local:

..  _developer-discount-provider:

VoucherDiscountProvider: one more DiscountProviderInterface implementation
==============================================================================

The core defines a generic contract for anything that lowers what a customer pays —
:php:`GoldeneZeiten\Products\Core\Discount\DiscountProviderInterface`, tagged
:php:`#[AutoconfigureTag('products.discount_provider')]` so Symfony's autowiring auto-discovers any
implementation without a manual :file:`Services.yaml` entry. This extension's
:php:`GoldeneZeiten\Products\Voucher\Discount\VoucherDiscountProvider` is its only implementation —
the core's :php:`DiscountRegistry` treats it exactly like any third-party discount an integrator
might add.

..  code-block:: php

    interface DiscountProviderInterface
    {
        public function getIdentifier(): string;
        public function getPriority(): int;
        public function quote(DiscountContext $context): array;
        public function apply(Order $order, DiscountContext $context): void;
    }

:php:`VoucherDiscountProvider::getIdentifier()`
    Returns the class constant :php:`VoucherDiscountProvider::IDENTIFIER` (:code:`'voucher'`) —
    the identifier every base voucher-discount adjustment is tagged with. A second, related
    identifier, :php:`VoucherDiscountProvider::FREE_SHIPPING` (:code:`'voucher.free_shipping'`),
    tags the separate adjustment a free-shipping voucher contributes (see below). Both constants
    are owned entirely by this class; the core never references them.

:php:`VoucherDiscountProvider::getPriority()`
    Returns ``0`` (the default priority). Its free-shipping offset does not depend on the ordering
    of other *discount* providers — it reads the carrier's own shipping adjustment, which the core
    places into the accumulated adjustment set before any discount provider runs at all.

Quote phase
-------------

:php:`quote(DiscountContext $context): CheckoutAdjustment[]` is read-only: it must never mark a
voucher used or write anything, since it may be called any number of times (every basket page view,
every checkout review re-render).

1.  It resolves the codes currently applied — read via
    :php:`VoucherCheckoutState::getCodes($context->getRequest())`, not from the basket DTO (see
    :ref:`Checkout state, not the basket DTO <developer-checkout-state>` below) — against
    :php:`VoucherService::resolveAllOrFail()`, which validates every code strictly and throws
    :php:`VoucherRedemptionFailedException` (wrapping the underlying
    :php:`VoucherExceptionInterface`) if any of them is no longer valid.
2.  If any vouchers resolved, it returns one :php:`CheckoutAdjustment` of type
    :php:`AdjustmentType::DISCOUNT`, identifier :php:`self::IDENTIFIER`, with the combined discount
    total negated (:php:`Money::fromCents(-$cents)`), and metadata carrying the applied codes
    (``['codes' => 'CODE1,CODE2']``) for later reconciliation.
3.  If any applied voucher waives shipping, a **second** adjustment is added, identifier
    :php:`self::FREE_SHIPPING`: it walks
    :php:`$context->getAccumulatedAdjustments()->byType(AdjustmentType::SHIPPING)`, sums whatever
    was contributed by :php:`CoreAdjustmentProvider::SHIPPING`, and returns that amount negated —
    offsetting the carrier's own cost without either side knowing about the other. The shop's
    separate bulky-item surcharge is a different adjustment and is never touched by this offset.

..  code-block:: php

    private function freeShippingOffset(array $vouchers, DiscountContext $context): Money
    {
        if (!$this->anyWaivesShipping($vouchers)) {
            return Money::fromCents(0);
        }
        $carrierCost = Money::fromCents(0);
        foreach ($context->getAccumulatedAdjustments()->byType(AdjustmentType::SHIPPING) as $adjustment) {
            if ($adjustment->getProviderIdentifier() === CoreAdjustmentProvider::SHIPPING) {
                $carrierCost = $carrierCost->add($adjustment->getAmount());
            }
        }
        return $this->negate($carrierCost);
    }

Apply phase
-------------

:php:`apply(Order $order, DiscountContext $context): void` runs inside the order transaction, once
per placed order:

1.  Re-resolves the same codes (throwing the same way `quote()` does if one has since become
    invalid — this rolls the whole order placement back).
2.  For each voucher, calls :php:`VoucherService::redeemAtomically()`, which increments
    ``redemption_count`` with an ``UPDATE ... WHERE redemption_count < usage_limit`` guard —
    protecting against two concurrent orders both redeeming the last remaining use of a limited
    code.
3.  Writes one :php:`VoucherRedemption` row per code (voucher UID/code, order UID, customer, the
    discount amount actually applied, a timestamp) via :php:`VoucherRedemptionRepository`, then
    calls :php:`persistAll()`.
4.  Dispatches one :php:`VoucherRedeemedEvent` per redeemed voucher.

..  _developer-checkout-state:

Checkout state, not the basket DTO
=====================================

The core's basket DTO carries only items and quantities — it is feature-agnostic, so nothing
voucher-specific is added to it. Instead, the codes a shopper enters live in this extension's own
slice of session storage:

-   **Customer enters a code:** :php:`GoldeneZeiten\Products\Voucher\Controller\VoucherController`
    (actions :php:`applyAction()`/:php:`removeAction()`) accepts the form submission — registered
    onto the core's own :guilabel:`ProductsCore`/:guilabel:`Basket` plugin via
    :php:`ExtensionUtility::registerControllerActions()` (see
    :ref:`Introduction <introduction-basket-ui>`), not a plugin of its own.

-   **Codes are stored:**
    :php:`GoldeneZeiten\Products\Voucher\Discount\VoucherCheckoutState` persists them via the core's
    :php:`GoldeneZeiten\Products\Core\Service\Checkout\CheckoutStateStore`, under its own private
    key (:code:`'voucher'`) — the core's checkout-state store never has to know a voucher from any
    other feature's state slice.

-   **State is read during quote/apply:** Both :php:`VoucherDiscountProvider` methods read the codes
    off :php:`$context->getRequest()` via :php:`VoucherCheckoutState::getCodes()`, rather than
    :php:`DiscountContext` carrying voucher-specific data.

-   **The basket UI renders the state:**
    :php:`GoldeneZeiten\Products\Voucher\ViewHelpers\Discount\VoucherSummaryViewHelper` builds a
    leniently-computed summary (an applied code that has since become invalid is silently left out,
    since the basket page only shows an estimate — placement re-validates strictly) for the
    ``Discount/VoucherBasket`` partial that overrides the core's no-op stub.

Integrators building a comparable custom discount feature (a loyalty-points selector, say) can follow
the same four-step shape: a small checkout-state holder wrapping :php:`CheckoutStateStore` under its
own key, a controller that accepts the input, a discount provider that reads the state during
:php:`quote()`/`apply()`, and a ViewHelper or partial that renders it — all without the basket DTO or
the core ever needing to change.

..  _developer-events:

Events
======

Both events are plain constructor-only value objects dispatched via
:php:`Psr\EventDispatcher\EventDispatcherInterface` (not TYPO3's :php:`#[AsEventListener]`-only core
event bus specifically, though listeners are wired the same way).

VoucherGeneratedEvent
------------------------

:php:`GoldeneZeiten\Products\Voucher\Event\VoucherGeneratedEvent` — dispatched by
:php:`GainedVoucherService::maybeIssue()` right after a reward voucher is persisted. Carries the
generated :php:`Voucher` and the :php:`Order` that triggered it.

..  code-block:: php

    #[AsEventListener]
    final class NotifyCustomerOfRewardVoucher
    {
        public function __invoke(VoucherGeneratedEvent $event): void
        {
            $voucher = $event->getVoucher();
            $order = $event->getOrder();
            // Notify the customer about their reward, sync it to a loyalty system, ...
        }
    }

VoucherRedeemedEvent
------------------------

:php:`GoldeneZeiten\Products\Voucher\Event\VoucherRedeemedEvent` — dispatched once per voucher by
:php:`VoucherDiscountProvider::apply()`, after the redemption row has been persisted. Carries the
redeemed :php:`Voucher`, the :php:`Order`, and the :php:`Money` discount amount actually applied.

..  code-block:: php

    #[AsEventListener]
    final class TrackVoucherRedemption
    {
        public function __invoke(VoucherRedeemedEvent $event): void
        {
            $voucher = $event->getVoucher();
            $order = $event->getOrder();
            $discount = $event->getDiscountAmount();
            // Track the redemption in an external loyalty/reporting system, ...
        }
    }

..  note::
    :php:`IssueGainedVoucherListener` — the listener that calls
    :php:`GainedVoucherService::maybeIssue()` in the first place — listens for the core's own
    :php:`GoldeneZeiten\Products\Core\Event\AfterOrderPlacedEvent`, and catches every
    :php:`\Throwable` around the whole gained-voucher attempt: a failure to issue a reward voucher
    (e.g. a code-generation collision) is logged but must never roll back an order that has already
    been placed.

..  _developer-order-detail-panel:

Backend order-detail panel
=============================

The backend order module's detail view exposes a tagged extension point for add-ons that attach
their own data to an order —
:php:`GoldeneZeiten\Products\Core\Backend\OrderDetail\OrderDetailPanelInterface`, tagged
:php:`#[AutoconfigureTag('products.order_detail_panel')]` the same way the discount interface is.
:php:`GoldeneZeiten\Products\Voucher\Backend\OrderDetail\VoucherRedemptionPanel` is this extension's
implementation: given an order UID, it returns a rendered HTML fragment (or :code:`null` when there
is nothing to show for that order) listing the redemptions and any gained voucher the order
generated, queried directly against the two voucher tables rather than through Extbase.

..  code-block:: php

    interface OrderDetailPanelInterface
    {
        public function renderForOrder(int $orderUid): ?string;
    }

The panel is registered ``public: true`` in :file:`Configuration/Services.yaml` purely so a
functional test can fetch it directly — its only runtime consumer is the core's tagged panel-registry
iterator, which would otherwise inline it into a non-fetchable service.

Registration
============

Every class in this extension is autowired through the single
``GoldeneZeiten\Products\Voucher\: resource: '../Classes/*'`` block in
:file:`Configuration/Services.yaml`. Nothing needs a manual service definition beyond that block and
the panel's :code:`public: true` override — :php:`VoucherDiscountProvider`,
:php:`IssueGainedVoucherListener` and :php:`VoucherRedemptionPanel` are all discovered purely through
the :php:`#[AutoconfigureTag]`/:php:`#[AsEventListener]` attributes on the interfaces and classes
themselves.
