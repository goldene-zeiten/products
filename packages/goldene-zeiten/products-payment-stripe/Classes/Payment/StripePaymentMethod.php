<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\WebhookResolution;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\StaticWebhookPaymentMethodInterface;
use GoldeneZeiten\Products\Payment\Stripe\Client\StripeClientFactory;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfiguration;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfigurationFactory;
use GoldeneZeiten\Products\Payment\Stripe\Event\ModifyStripeSessionRequestEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\Webhook;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Stripe Checkout as a redirect payment method: the customer is sent to Stripe's hosted checkout to pay,
 * returns to the shop where the session is confirmed server-to-server, and Stripe's webhook confirms the
 * same session. The browser return and the webhook are both untrusted and both idempotent - a return
 * after the order is already paid, or a replayed webhook, is a no-op.
 */
final class StripePaymentMethod implements RedirectPaymentMethodInterface, StaticWebhookPaymentMethodInterface
{
    public const IDENTIFIER = 'stripe';

    private const SESSION_ID_PLACEHOLDER = 'session_id={CHECKOUT_SESSION_ID}';

    private const EVENT_COMPLETED = 'checkout.session.completed';
    private const EVENT_ASYNC_SUCCEEDED = 'checkout.session.async_payment_succeeded';
    private const EVENT_ASYNC_FAILED = 'checkout.session.async_payment_failed';

    private const HANDLED_EVENTS = [
        self::EVENT_COMPLETED,
        self::EVENT_ASYNC_SUCCEEDED,
        self::EVENT_ASYNC_FAILED,
    ];

    public function __construct(
        private readonly StripeConfigurationFactory $configurationFactory,
        private readonly StripeClientFactory $clientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OrderRepository $orderRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_stripe', 'ProductsPaymentStripe');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return $this->configurationFactory->forCurrentRequest()->isComplete() && $context->getCurrency() !== '';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        if ($context->getReturnUrl() === '' || $context->getCancelUrl() === '') {
            return PaymentResult::failed('Stripe needs a configured checkout page for its return URLs.');
        }
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $session = $this->clientFactory->create($configuration)
                ->checkout->sessions->create($this->sessionParameters($order, $context, $configuration));
        } catch (ApiErrorException $exception) {
            $this->logger->error('Stripe session creation failed.', ['exception' => $exception]);
            return PaymentResult::failed('The payment could not be started. Please try again.');
        }

        return PaymentResult::redirectRequired((string)$session->url, (string)$session->id);
    }

    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
    {
        if ($order->getPaymentStatus() === PaymentStatus::PAID) {
            return PaymentResult::completed(PaymentStatus::PAID);
        }
        $sessionId = (string)($request->getQueryParams()['session_id'] ?? '');
        if ($sessionId === '') {
            return PaymentResult::failed('Stripe did not return a session id on the return URL.');
        }

        return $this->confirm($sessionId, $order);
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        $event = $this->verifiedEvent($request);
        if ($event === null) {
            return PaymentResult::failed('Stripe webhook signature could not be verified.');
        }
        if (!in_array($event->type, self::HANDLED_EVENTS, true)) {
            return PaymentResult::unchanged();
        }

        return $this->interpretEvent($event, $order);
    }

    /**
     * Stripe's webhook is one endpoint registered in the dashboard, so the callback carries no per-order
     * token. The `Stripe-Signature` header authenticates the body; only once it verifies is the session's
     * `client_reference_id` trusted to name the order {@see StaticWebhookPaymentMethodInterface}.
     *
     * Subscribe the endpoint to `checkout.session.completed`, `checkout.session.async_payment_succeeded` and
     * `checkout.session.async_payment_failed`; any other event type is acknowledged and ignored.
     */
    public function handleStaticWebhook(ServerRequestInterface $request): WebhookResolution
    {
        $event = $this->verifiedEvent($request);
        if ($event === null) {
            return WebhookResolution::unresolved(PaymentResult::failed('Stripe webhook signature could not be verified.'));
        }
        if (!in_array($event->type, self::HANDLED_EVENTS, true)) {
            return WebhookResolution::ignored();
        }
        $order = $this->orderRepository->findByUid((int)($event->data->object->client_reference_id ?? 0));
        if ($order === null) {
            return WebhookResolution::unresolved(PaymentResult::failed('The Stripe webhook references an unknown order.'));
        }

        return WebhookResolution::resolved($order, $this->interpretEvent($event, $order));
    }

    private function verifiedEvent(ServerRequestInterface $request): ?Event
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            return Webhook::constructEvent(
                (string)$request->getBody(),
                $request->getHeaderLine('Stripe-Signature'),
                $configuration->webhookSecret,
            );
        } catch (\UnexpectedValueException | SignatureVerificationException) {
            return null;
        }
    }

    /**
     * An asynchronous payment (SEPA debit, bank transfer) completes the checkout session unpaid and settles
     * later with a separate success or failure event; the failure carries the still-unpaid session.
     */
    private function interpretEvent(Event $event, Order $order): PaymentResult
    {
        $session = $event->data->object;
        if ($event->type !== self::EVENT_ASYNC_FAILED) {
            return $this->interpretSession($session, $order);
        }
        if (!$this->belongsToOrder($session, $order)) {
            return PaymentResult::unchanged();
        }

        return PaymentResult::failed('The Stripe payment failed.', (string)($session->payment_intent ?? $session->id));
    }

    private function confirm(string $sessionId, Order $order): PaymentResult
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $session = $this->clientFactory->create($configuration)->checkout->sessions->retrieve($sessionId);
        } catch (ApiErrorException $exception) {
            $this->logger->error('Stripe session retrieval failed.', ['exception' => $exception]);
            return PaymentResult::failed('The Stripe payment could not be verified.', $sessionId);
        }

        return $this->interpretSession($session, $order);
    }

    /**
     * A valid callback token only proves the caller owns *some* order of this shop; the `session_id` on the
     * return URL is a separate, caller-controlled value. Binding the session to this order - by the
     * `client_reference_id` set at creation, the settled amount and the currency - stops a cheap paid session
     * from being replayed onto an expensive unpaid order.
     *
     * A completed session that is still unpaid is an asynchronous payment on its way, so it is pending; one
     * that never completed (expired, abandoned) failed.
     */
    private function interpretSession(StripeObject $session, Order $order): PaymentResult
    {
        $externalId = (string)($session->payment_intent ?? $session->id);
        $isSettled = in_array((string)($session->payment_status ?? ''), ['paid', 'no_payment_required'], true);
        if (!$isSettled && (string)($session->status ?? '') !== 'complete') {
            return PaymentResult::failed('The Stripe payment was not completed.', $externalId);
        }
        if (!$this->belongsToOrder($session, $order)) {
            $this->logger->warning('Stripe session does not match the order it was returned for.', [
                'order' => $order->getUid(),
                'session' => (string)($session->id ?? ''),
            ]);
            return PaymentResult::failed('The Stripe payment did not match the order.', $externalId);
        }

        return $isSettled ? PaymentResult::completed(PaymentStatus::PAID, $externalId) : PaymentResult::pending($externalId);
    }

    private function belongsToOrder(StripeObject $session, Order $order): bool
    {
        $amountTotal = $session->amount_total ?? null;

        return (string)($session->client_reference_id ?? '') === (string)($order->getUid() ?? 0)
            && $amountTotal !== null
            && (int)$amountTotal === $order->getTotalGross()->getCents()
            && strtolower((string)($session->currency ?? '')) === strtolower($order->getCurrency());
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionParameters(Order $order, PaymentContext $context, StripeConfiguration $configuration): array
    {
        $event = new ModifyStripeSessionRequestEvent(
            $this->buildSessionParameters($order, $context),
            $order,
            $context,
            $configuration,
        );
        $this->eventDispatcher->dispatch($event);

        return $event->getPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSessionParameters(Order $order, PaymentContext $context): array
    {
        $parameters = [
            'mode' => 'payment',
            'client_reference_id' => (string)($order->getUid() ?? 0),
            'success_url' => $this->successUrl($context->getReturnUrl()),
            'cancel_url' => $context->getCancelUrl(),
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($context->getCurrency()),
                        'unit_amount' => $context->getAmount()->getCents(),
                        'product_data' => [
                            'name' => 'Order ' . $order->getOrderNumber(),
                        ],
                    ],
                ],
            ],
        ];
        if ($order->getEmail() !== '') {
            $parameters['customer_email'] = $order->getEmail();
        }

        return $parameters;
    }

    /**
     * Stripe replaces the literal `{CHECKOUT_SESSION_ID}` placeholder on the return, so `handleReturn()`
     * can read the session back. The shop's own token lives under the checkout plugin namespace, so this
     * bare `session_id` parameter never collides with it.
     */
    private function successUrl(string $returnUrl): string
    {
        return $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . self::SESSION_ID_PLACEHOLDER;
    }
}
