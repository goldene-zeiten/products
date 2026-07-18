..  include:: /Includes.rst.txt
..  _developer-api-payment-methods:

====================
Payment Methods API
====================

The Payment Methods API enables integrators to implement payment gateways for credit cards,
digital wallets, invoice programs, bank transfers, or any other transaction processor the shop
needs. A payment method is a service that receives an order, **may charge a fee**, and either
completes the payment immediately or redirects the customer to an external gateway and handles
the callback. Because payment is inherently shop-specific, **the extension ships with only
invoice payment** (pay by bank transfer); integrators add payment gateways by implementing
the interfaces.

**Location:** :php:`GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface`

Lifecycle: Registration, Discovery, Fee, Execution, and Callbacks
=================================================================

The payment method API operates across five distinct phases:

**1. Registration** — A service implementing :php:`PaymentMethodInterface` is automatically
discovered and registered by the :php:`PaymentMethodRegistry` thanks to the
:php:`#[AutoconfigureTag('products.payment_method')]` attribute on the interface. No manual
entry in :file:`Services.yaml` is required.

**2. Discovery** — During checkout, the frontend asks :php:`PaymentMethodRegistry::getAvailable(PaymentContext)`:
which methods may the customer choose from? The registry filters all registered methods by
calling :php:`isAvailable()` on each, then sorts by :php:`getPriority()` (highest first).
The result is dispatched to :php:`PaymentMethodsCollectedEvent` so listeners can reorder or
hide methods. The customer sees only the methods that passed the availability check.

**3. Fee** — Each available method may declare a surcharge via :php:`calculateFee()`, returned
in cents. This surcharge is added as a :php:`PAYMENT_FEE` :php:`CheckoutAdjustment` (see the
:ref:`Checkout Adjustments API <developer-api-checkout-adjustments>`) and is applied **last**,
after discounts and other adjustments. This means the fee is calculated on what the customer
actually owes after discounts.

**4. Execution** — When the customer confirms the order and selects a payment method, the
checkout calls :php:`initiate(Order, PaymentContext)`. The method returns a :php:`PaymentResult`
that signals whether the payment succeeded immediately, is pending, or requires a redirect to
a gateway.

**5. Callbacks** — If the method implements :php:`RedirectPaymentMethodInterface`, the customer
is redirected to an external gateway. The gateway can call back twice: the customer's browser
returns to a checkout action, and the gateway posts an asynchronous confirmation to a fixed
webhook middleware. Both callbacks are idempotent; the method's handlers must verify the
callback against the gateway before reporting success.

Interface Contract
==================

..  code-block:: php

    interface PaymentMethodInterface
    {
        public function getIdentifier(): string;
        public function getLabel(): string;
        public function isAvailable(PaymentContext $context): bool;
        public function getPriority(): int;
        public function calculateFee(PaymentContext $context): int;
        public function initiate(Order $order, PaymentContext $context): PaymentResult;
    }

**Methods:**

:php:`getIdentifier(): string`
    A unique identifier for this method (e.g., :code:`'stripe'`, :code:`'paypal'`,
    :code:`'klarna_invoice'`). Used to resolve which method the customer selected.

:php:`getLabel(): string`
    Human-readable label shown during checkout (e.g., :code:`'Credit Card via Stripe'`).

:php:`isAvailable(PaymentContext $context): bool`
    **Discovery phase:** Return :code:`true` if this method may be offered to the customer
    for the given amount, country, and currency. A method that only supports certain
    currencies or countries, or only for amounts above a minimum, returns :code:`false`
    and is not offered. Called once per method during discovery.

:php:`getPriority(): int`
    Higher values are offered first. Methods sharing the same priority keep their
    registration order. Use :code:`0` for default priority. The built-in invoice method
    returns :code:`0`, so an integrator's method can rank above it by using a higher value.

:php:`calculateFee(PaymentContext $context): int`
    **Discovery phase:** Return the surcharge for this method in cents (e.g., :code:`299`
    for a 2.99 EUR credit card fee). The fee is added as a :php:`PAYMENT_FEE` adjustment
    during order creation and is applied after discounts. Return :code:`0` for no fee.

:php:`initiate(Order $order, PaymentContext $context): PaymentResult`
    **Execution phase:** Start the payment for this order. Return one of the :php:`PaymentResult`
    factory methods (see below) to signal the outcome.

PaymentContext
===============

An immutable, read-only value object passed to both :php:`isAvailable()` and :php:`initiate()`.
It carries:

:php:`getAmount(): Money`
    The amount the customer must pay, in cents. For a redirect method, this is the order
    total including all adjustments and the payment method's own fee.

:php:`getCurrency(): string`
    The order currency (e.g., :code:`'EUR'`, :code:`'USD'`).

:php:`getCountryCode(): string`
    The customer's billing country (ISO 3166-1 alpha-2, e.g., :code:`'DE'`, :code:`'US'`).

:php:`getFrontendUserUid(): int`
    The UID of the logged-in frontend user (0 if guest).

:php:`getReturnUrl(): string`
    (Execution phase only) Absolute URL where the customer's browser should return after
    the gateway confirmation. Empty if no checkout page is configured. Built by
    :php:`PaymentUrlFactory`, HMAC-signed per order. Only set during :php:`initiate()`,
    not during discovery.

:php:`getCancelUrl(): string`
    (Execution phase only) Absolute URL where to send the customer if they abandon payment
    at the gateway. Also built and signed by :php:`PaymentUrlFactory`, empty if checkout
    page is not configured.

:php:`getWebhookUrl(): string`
    (Execution phase only) Absolute URL where the gateway posts its asynchronous confirmation.
    Always set (does not require a checkout page); this is a fixed middleware path, not a
    plugin action. Also signed per order.

PaymentResult
==============

An immutable value object returned by :php:`initiate()`, :php:`handleReturn()`, and
:php:`handleWebhook()`. Use the static factory methods to construct it:

:php:`PaymentResult::completed(PaymentStatus $status, string $externalId = '', array $rawData = []): self`
    The payment succeeded or failed synchronously (no gateway involved, or the gateway's
    response arrived inline). The :php:`$status` is one of :php:`PaymentStatus::PAID`,
    :php:`PaymentStatus::PENDING`, or :php:`PaymentStatus::FAILED`. The optional
    :php:`$externalId` is the gateway's transaction ID; store it in the order for
    reconciliation. The optional :php:`$rawData` is the raw gateway response, also stored.

:php:`PaymentResult::pending(string $externalId = '', array $rawData = []): self`
    The payment is asynchronous (e.g., bank transfer in progress, invoice sent). The order
    remains in :php:`PaymentStatus::PENDING` until the gateway confirms via a webhook or
    the backend updates it manually. Shorthand for
    :code:`completed(PaymentStatus::PENDING, ...)`.

:php:`PaymentResult::redirectRequired(string $redirectUrl, string $externalId = ''): self`
    The customer must be redirected to the gateway. The :php:`$redirectUrl` is the absolute
    URL to the gateway's payment form or checkout. The checkout will redirect the browser
    to this URL after creating the order. The optional :php:`$externalId` is the gateway's
    transaction or session ID; store it for webhook reconciliation.

:php:`PaymentResult::failed(string $reason, string $externalId = ''): self`
    The payment failed. The :php:`$reason` is a human-readable error message for the backend
    (e.g., :code:`'Insufficient funds'`). The order transitions to :php:`PaymentStatus::FAILED`.
    Return this if the gateway rejects the payment synchronously, or if you detect a problem
    before contacting the gateway.

Registration
============

The interface carries the :php:`#[AutoconfigureTag('products.payment_method')]` attribute,
so any class implementing :php:`PaymentMethodInterface` is automatically registered — no
manual entry in :file:`Configuration/Services.yaml` is required, as long as your extension's
:file:`Services.yaml` has :code:`autoconfigure: true` (the Symfony/TYPO3 default).

The :php:`PaymentMethodsCollectedEvent` is **NOT** how you register a method — registration
happens by implementing the interface. The event exists to let listeners reorder or filter
the already-collected list after discovery, for cases where a filter decision is made at
discovery time rather than at implementation time.

Filtering After Discovery: PaymentMethodsCollectedEvent
========================================================

After the registry collects and sorts the available methods, it dispatches
:php:`PaymentMethodsCollectedEvent`. This event is **mutable** via
:php:`setPaymentMethods()` and allows listeners to reorder or hide methods. Common use cases:

-   Hide a payment method for certain user roles or order amounts.
-   Promote an integrator's method to the top if a gateway is available.
-   Remove a method that failed a pre-flight check (e.g., API quota exhausted).

Redirect Payment Methods: Handling Gateway Callbacks
====================================================

For gateway-backed payment methods, implement :php:`RedirectPaymentMethodInterface` instead
of :php:`PaymentMethodInterface`. This interface extends :php:`PaymentMethodInterface` and
adds two callback methods:

..  code-block:: php

    interface RedirectPaymentMethodInterface extends PaymentMethodInterface
    {
        public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult;
        public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult;
    }

**Execution Flow**

1. :php:`initiate()` returns :php:`PaymentResult::redirectRequired($gatewayUrl)`. The
   checkout creates the order and redirects the customer to the gateway URL.

2. The customer completes or cancels payment at the gateway. The gateway redirects the
   customer's browser back to :php:`PaymentContext::getReturnUrl()` (or :php:`getCancelUrl()`
   if they abandon).

3. The checkout's :php:`CheckoutController::paymentReturnAction()` calls
   :php:`PaymentCallbackService::handleReturn()`, which invokes :php:`handleReturn()` with
   the request and order. This method verifies the callback against the gateway and returns
   a :php:`PaymentResult`.

4. **Independently**, the gateway posts an asynchronous confirmation (webhook) to
   :php:`PaymentContext::getWebhookUrl()`. This request hits the
   :php:`PaymentWebhookMiddleware` (registered at path :code:`/products/payment/webhook`),
   which calls :php:`PaymentCallbackService::handleWebhook()`, invoking :php:`handleWebhook()`
   on your method.

5. Both callbacks invoke :php:`OrderFinalizationService::finalize()`, which applies the
   :php:`PaymentResult` to the order (e.g., changing its payment status).

**Idempotency and Replay Safety**

Both callbacks are **replayable**:

-   A customer may reload the return page (their browser makes the same request again).
-   A gateway may retry its webhook if it did not receive a success response.

Your :php:`handleReturn()` and :php:`handleWebhook()` must be idempotent — calling them
multiple times with the same request must not duplicate payments or transactions. Common
strategies:

-   Look up the gateway's transaction ID in your :php:`PaymentTransaction` table. If it
    already exists, return a result for that transaction instead of creating a new one.
-   Check the order's current payment status. If already paid, return success. If already
    failed, return failure.

**Critical Security Notice: Verify with the Gateway**

The signed token on the callback URL only proves the URL was issued by this shop for this
order. **It does NOT prove the payment succeeded.** A customer could:

-   Manually craft a :code:`return` URL with a valid token and visit it.
-   Capture a webhook request and replay it.
-   Modify the query string to claim success.

**Your :php:`handleReturn()` and :php:`handleWebhook()` methods MUST verify the callback
against the gateway before reporting success.** Common verification steps:

1. Extract the transaction ID or session ID from the request (e.g., query string, request body).
2. Call the gateway's API (e.g., :code:`GET /transactions/{id}` or :code:`POST /verify`) to
   fetch the transaction status server-to-server.
3. Verify the gateway's response:
   -   For webhooks, verify the webhook's HMAC signature (most gateways include one).
   -   For the return URL, query the gateway directly; do not trust the customer's device.
4. Only then return :php:`PaymentResult::completed(PaymentStatus::PAID)`.

If verification fails, return :php:`PaymentResult::failed('...')` so the order remains unpaid
and the backend can investigate.

Callback URLs and Configuration
=================================

The return and cancel URLs are built from the checkout page. If :code:`products.pids.checkoutPage`
is not configured in the site's settings, these URLs are empty strings. A redirect method
may check for empty URLs and either fail gracefully or omit those URLs from the gateway
request (some gateways allow async confirmation without a return URL).

The webhook URL is always set (it is a fixed middleware path and does not require a checkout
page). It is absolute and HMAC-signed per order.

Refundable Payment Methods
============================

For payment methods that support refunds or cancellations (e.g., credit card providers),
additionally implement :php:`RefundablePaymentMethodInterface`:

..  code-block:: php

    interface RefundablePaymentMethodInterface
    {
        public function cancel(OrderData $order): PaymentResult;
        public function refund(OrderData $order, Money $amount): PaymentResult;
    }

:php:`cancel(OrderData $order): PaymentResult`
    The backend is cancelling the order entirely. If the payment was initiated with a
    gateway, reverse it (refund 100%, void the authorization, or cancel the invoice).
    Return a :php:`PaymentResult` describing the outcome. The :php:`$order` is a
    :php:`GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData` snapshot; access its
    properties directly (e.g., :php:`$order->invoiceNumber`, :php:`$order->status`).

:php:`refund(OrderData $order, Money $amount): PaymentResult`
    The backend is issuing a partial or full refund for an amount. Call the gateway's
    refund API. Return a :php:`PaymentResult` describing the outcome. The :php:`$order`
    is a :php:`GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData` snapshot. The
    :php:`$amount` is a :php:`Money` value object; call :php:`getAmount()` to get the
    cents value.

The backend only offers refund/cancel actions if the order's payment method implements
:php:`RefundablePaymentMethodInterface` (checked via :code:`instanceof`). Methods that do
not support refunds (e.g., bank transfers) simply do not implement this interface.

PaymentMethodRegistry
=====================

The registry is the service point for discovering and resolving methods:

:php:`getAvailable(PaymentContext $context): array<PaymentMethodInterface>`
    Returns the list of available (filtered and sorted) methods for the given context.
    Dispatches :php:`PaymentMethodsCollectedEvent` at the end of collection.

:php:`get(string $identifier): PaymentMethodInterface`
    Resolves a method by identifier. Throws :php:`PaymentMethodNotFoundException` (code
    1751751010) if unknown — useful for catching configuration or data errors.

Example: Stripe Redirect Payment Method
=========================================

This example implements a credit card payment method backed by Stripe's Hosted Checkout:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Payment;

    use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
    use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
    use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
    use GoldeneZeiten\Products\Core\Domain\Model\Order;
    use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
    use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Stripe\Checkout\Session;
    use Stripe\Exception\ApiErrorException;
    use Stripe\StripeClient;

    /**
     * Stripe Checkout payment method: customer is redirected to Stripe's hosted checkout,
     * and we handle both the browser return and async webhook callback.
     */
    final class StripeCheckoutPaymentMethod implements RedirectPaymentMethodInterface
    {
        public function __construct(
            private readonly StripeClient $stripeClient,
            private readonly string $stripeApiKey
        ) {}

        public function getIdentifier(): string
        {
            return 'stripe_checkout';
        }

        public function getLabel(): string
        {
            return 'Credit Card (Stripe)';
        }

        public function isAvailable(PaymentContext $context): bool
        {
            // Stripe supports EUR and USD; only offer if in those currencies
            $supportedCurrencies = ['EUR', 'USD'];

            return in_array($context->getCurrency(), $supportedCurrencies, true);
        }

        public function getPriority(): int
        {
            return 50; // Offer before invoice (priority 0)
        }

        public function calculateFee(PaymentContext $context): int
        {
            // Stripe charges 1.4% + 0.20 EUR per transaction
            $percent = (int)($context->getAmount()->getAmount() * 0.014);
            $fixed = 20; // cents
            return $percent + $fixed;
        }

        public function initiate(Order $order, PaymentContext $context): PaymentResult
        {
            try {
                // Create a Stripe Checkout Session
                $session = $this->stripeClient->checkout->sessions->create([
                    'payment_method_types' => ['card'],
                    'mode' => 'payment',
                    'client_reference_id' => (string)$order->getUid(),
                    'customer_email' => $order->getEmail(),
                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => strtolower($context->getCurrency()),
                                'unit_amount' => (int)$context->getAmount()->getAmount(),
                                'product_data' => [
                                    'name' => 'Order ' . $order->getOrderNumber(),
                                ],
                            ],
                            'quantity' => 1,
                        ],
                    ],
                    'success_url' => $context->getReturnUrl(),
                    'cancel_url' => $context->getCancelUrl(),
                ]);

                return PaymentResult::redirectRequired($session->url, $session->id);
            } catch (ApiErrorException $exception) {
                return PaymentResult::failed('Failed to create Stripe session: ' . $exception->getMessage(), '');
            }
        }

        public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
        {
            // The return URL is accessed by the customer's browser after Stripe closes.
            // We must verify the payment status via Stripe's API, not trust what the browser sends.

            // Extract the session ID from the URL query string (Stripe sends it as 'session_id')
            $queryParams = $request->getQueryParams();
            $sessionId = (string)($queryParams['session_id'] ?? '');

            if ($sessionId === '') {
                return PaymentResult::failed('No Stripe session ID in return URL', '');
            }

            // Verify with the gateway here: fetch the session from Stripe
            try {
                $session = $this->stripeClient->checkout->sessions->retrieve($sessionId);
            } catch (ApiErrorException $exception) {
                return PaymentResult::failed('Failed to verify Stripe session: ' . $exception->getMessage(), $sessionId);
            }

            // Check the payment status
            if ($session->payment_status === 'paid') {
                return PaymentResult::completed(
                    PaymentStatus::PAID,
                    $session->payment_intent, // Store Stripe's payment intent ID
                    ['session_id' => $session->id, 'raw_session' => $session->toArray()]
                );
            }

            if ($session->payment_status === 'unpaid') {
                return PaymentResult::failed('Customer did not complete payment', $session->id);
            }

            // For 'open' or unknown states, mark as pending
            return PaymentResult::pending($session->payment_intent);
        }

        public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
        {
            // Parse the webhook body
            $body = (string)$request->getBody();
            $event = json_decode($body, true);

            if ($event === null) {
                return PaymentResult::failed('Invalid webhook JSON', '');
            }

            $eventType = $event['type'] ?? '';
            $stripeData = $event['data']['object'] ?? [];

            // Verify with the gateway here: check the webhook signature
            $signature = (string)($request->getServerParams()['HTTP_STRIPE_SIGNATURE'] ?? '');
            if (!$this->verifyStripeWebhookSignature($body, $signature)) {
                return PaymentResult::failed('Invalid Stripe webhook signature', '');
            }

            // Handle the specific event types
            if ($eventType === 'checkout.session.completed') {
                $sessionId = $stripeData['id'] ?? '';
                $paymentStatus = $stripeData['payment_status'] ?? '';

                if ($paymentStatus === 'paid') {
                    return PaymentResult::completed(
                        PaymentStatus::PAID,
                        $stripeData['payment_intent'] ?? $sessionId,
                        ['session_id' => $sessionId, 'raw_data' => $stripeData]
                    );
                }
            }

            if ($eventType === 'charge.refunded') {
                // Handle refund webhook
                $chargeId = $stripeData['id'] ?? '';
                $amountRefunded = $stripeData['amount_refunded'] ?? 0;

                return PaymentResult::completed(
                    PaymentStatus::REFUNDED,
                    $chargeId,
                    ['amount_refunded' => $amountRefunded]
                );
            }

            // Unrecognized event type; acknowledge it to Stripe (return 200)
            // but do not update the order
            return PaymentResult::pending();
        }

        /**
         * Verify the Stripe webhook signature.
         */
        private function verifyStripeWebhookSignature(string $body, string $signature): bool
        {
            try {
                // Stripe's SDK will throw if signature is invalid
                \Stripe\Webhook::constructEvent($body, $signature, 'whsec_...');
                return true;
            } catch (\Stripe\Exception\SignatureVerificationException) {
                return false;
            }
        }
    }

**Configuration**

In your extension's :file:`Configuration/Services.yaml`, enable autowiring and
autoconfiguration:

..  code-block:: yaml

    services:
      _defaults:
        autowire: true
        autoconfigure: true

      MyVendor\MyExtension\Payment\:
        resource: '../Classes/Payment/*'

Your payment method class will be automatically discovered and registered.

Why This API Is an Interface, Not an Event
============================================

-   **Registry question:** "Which payment methods are available for this cart?" Events cannot
    answer this — a listener is optional and cannot decide what to offer.
-   **Priority and availability:** Each method declares its own availability and priority
    independently. Events cannot express this; only a service registry can.
-   **Resolution by identifier:** The customer selected method :code:`'stripe_checkout'`. The
    registry must resolve it to the actual method and invoke :php:`initiate()`. Events cannot do
    this; only a service can.
-   **Idempotent finalization:** Callbacks are replayed; the order finalization must be
    idempotent. A forgotten event listener silently does nothing. A forgotten implementation is
    obvious and fails immediately.

The event (:php:`PaymentMethodsCollectedEvent`) exists to allow last-minute filtering and
reordering — for cases where a filter decision is made at discovery time, not at implementation
time.
