<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use GoldeneZeiten\Products\Payment\Stripe\Client\StripeClientFactory;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfiguration;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfigurationFactory;
use GoldeneZeiten\Products\Payment\Stripe\Event\ModifyStripeSessionRequestEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
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
final class StripePaymentMethod implements RedirectPaymentMethodInterface
{
    public const IDENTIFIER = 'stripe';

    private const SESSION_ID_PLACEHOLDER = 'session_id={CHECKOUT_SESSION_ID}';

    public function __construct(
        private readonly StripeConfigurationFactory $configurationFactory,
        private readonly StripeClientFactory $clientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
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
            return PaymentResult::failed('Stripe session could not be created: ' . $exception->getMessage());
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

        return $this->confirm($sessionId);
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $event = Webhook::constructEvent(
                (string)$request->getBody(),
                $request->getHeaderLine('Stripe-Signature'),
                $configuration->webhookSecret,
            );
        } catch (\UnexpectedValueException | SignatureVerificationException) {
            return PaymentResult::failed('Stripe webhook signature could not be verified.');
        }
        if ($event->type !== 'checkout.session.completed') {
            return PaymentResult::pending();
        }

        return $this->interpretSession($event->data->object);
    }

    private function confirm(string $sessionId): PaymentResult
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $session = $this->clientFactory->create($configuration)->checkout->sessions->retrieve($sessionId);
        } catch (ApiErrorException $exception) {
            $this->logger->error('Stripe session retrieval failed.', ['exception' => $exception]);
            return PaymentResult::failed('Stripe session could not be verified: ' . $exception->getMessage(), $sessionId);
        }

        return $this->interpretSession($session);
    }

    private function interpretSession(StripeObject $session): PaymentResult
    {
        $externalId = (string)($session->payment_intent ?? $session->id);
        $paymentStatus = (string)($session->payment_status ?? '');
        if ($paymentStatus === 'paid') {
            return PaymentResult::completed(PaymentStatus::PAID, $externalId);
        }
        if ($paymentStatus === 'unpaid') {
            return PaymentResult::failed('The Stripe payment was not completed.', $externalId);
        }

        return PaymentResult::pending($externalId);
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
