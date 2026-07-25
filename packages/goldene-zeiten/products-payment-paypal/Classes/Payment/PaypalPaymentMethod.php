<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\WebhookResolution;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\StaticWebhookPaymentMethodInterface;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfigurationFactory;
use GoldeneZeiten\Products\Payment\Paypal\Domain\Dto\PaypalCapture;
use GoldeneZeiten\Products\Payment\Paypal\Exception\PaypalApiException;
use GoldeneZeiten\Products\Payment\Paypal\Order\PaypalOrderClient;
use GoldeneZeiten\Products\Payment\Paypal\Webhook\PaypalWebhookVerifier;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * PayPal Checkout as a redirect payment method: the customer is sent to PayPal to approve, returns to the
 * shop where the order is captured server-to-server, and PayPal's asynchronous webhook confirms the same
 * capture. The browser return and the webhook are both untrusted and both idempotent - a return after the
 * order is already paid, or a replayed capture, is a no-op.
 */
final class PaypalPaymentMethod implements RedirectPaymentMethodInterface, StaticWebhookPaymentMethodInterface
{
    public const IDENTIFIER = 'paypal';

    /**
     * The currencies PayPal settles in. Offering PayPal for anything else only fails at create-order time.
     */
    private const SUPPORTED_CURRENCIES = [
        'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY',
        'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD',
    ];

    public function __construct(
        private readonly PaypalConfigurationFactory $configurationFactory,
        private readonly PaypalOrderClient $orderClient,
        private readonly PaypalWebhookVerifier $webhookVerifier,
        private readonly OrderRepository $orderRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_paypal', 'ProductsPaymentPaypal');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return $this->configurationFactory->forCurrentRequest()->isComplete()
            && in_array(strtoupper($context->getCurrency()), self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Offered above invoice (priority 0) but leaving room for a card provider to rank higher.
     */
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
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $paypalOrder = $this->orderClient->createOrder($order, $context, $configuration);
        } catch (PaypalApiException $exception) {
            $this->logger->error('PayPal order creation failed.', ['exception' => $exception]);
            return PaymentResult::failed('The payment could not be started. Please try again.');
        }
        if ($paypalOrder->approveUrl === '') {
            return PaymentResult::failed('PayPal returned no approval URL.', $paypalOrder->id);
        }

        return PaymentResult::redirectRequired($paypalOrder->approveUrl, $paypalOrder->id);
    }

    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
    {
        if ($order->getPaymentStatus() === PaymentStatus::PAID) {
            // A replayed return after the first one already captured and finalized the order.
            return PaymentResult::completed(PaymentStatus::PAID);
        }
        // PayPal appends its order id to the return URL as the bare `token` query parameter; the shop's own
        // signed token lives under the checkout plugin namespace, so the two never collide.
        $paypalOrderId = (string)($request->getQueryParams()['token'] ?? '');
        if ($paypalOrderId === '') {
            return PaymentResult::failed('PayPal did not return an order token on the return URL.');
        }

        return $this->capture($paypalOrderId, $order);
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        $body = (string)$request->getBody();
        $event = json_decode($body, true);
        if (!is_array($event)) {
            return PaymentResult::failed('PayPal webhook body was not valid JSON.');
        }
        $configuration = $this->configurationFactory->forCurrentRequest();
        if (!$this->webhookVerifier->isSignatureValid($request, $body, $configuration)) {
            return PaymentResult::failed('PayPal webhook signature could not be verified.');
        }

        return $this->interpretEvent($event);
    }

    /**
     * PayPal's webhook is one endpoint registered in the dashboard, so the callback carries no per-order
     * token. The transmission signature authenticates the body; only once it verifies is the capture's
     * `custom_id` trusted to name the order, and its amount checked against that order's total
     * {@see StaticWebhookPaymentMethodInterface}.
     */
    public function handleStaticWebhook(ServerRequestInterface $request): WebhookResolution
    {
        $event = $this->verifiedWebhookEvent($request);
        if ($event === null) {
            return WebhookResolution::unresolved(PaymentResult::failed('PayPal webhook could not be verified.'));
        }
        if ((string)($event['event_type'] ?? '') !== 'PAYMENT.CAPTURE.COMPLETED') {
            return WebhookResolution::unresolved(PaymentResult::pending());
        }

        return $this->resolveCompletedCapture((array)($event['resource'] ?? []), $event);
    }

    /**
     * @return array<string, mixed>|null the verified event body, or null when the body or signature is invalid
     */
    private function verifiedWebhookEvent(ServerRequestInterface $request): ?array
    {
        $body = (string)$request->getBody();
        $event = json_decode($body, true);
        if (!is_array($event)) {
            return null;
        }
        $configuration = $this->configurationFactory->forCurrentRequest();

        return $this->webhookVerifier->isSignatureValid($request, $body, $configuration) ? $event : null;
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, mixed> $event
     */
    private function resolveCompletedCapture(array $resource, array $event): WebhookResolution
    {
        $order = $this->orderRepository->findByUid((int)($resource['custom_id'] ?? 0));
        if ($order === null) {
            return WebhookResolution::unresolved(PaymentResult::failed('The PayPal webhook references an unknown order.'));
        }
        if (!$this->webhookAmountMatches($resource, $order)) {
            $this->logger->warning('PayPal webhook capture does not match the order.', ['order' => $order->getUid()]);
            return WebhookResolution::unresolved(PaymentResult::failed('The PayPal payment did not match the order.'));
        }

        return WebhookResolution::resolved($order, PaymentResult::completed(PaymentStatus::PAID, (string)($resource['id'] ?? ''), $event));
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function webhookAmountMatches(array $resource, Order $order): bool
    {
        $amount = (array)($resource['amount'] ?? []);
        if (!isset($amount['value'])) {
            return false;
        }

        return Money::fromDecimalString((string)$amount['value'])->getCents() === $order->getTotalGross()->getCents()
            && strtoupper((string)($amount['currency_code'] ?? '')) === strtoupper($order->getCurrency());
    }

    private function capture(string $paypalOrderId, Order $order): PaymentResult
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $capture = $this->orderClient->capture($paypalOrderId, $configuration);
        } catch (PaypalApiException $exception) {
            $this->logger->error('PayPal capture failed.', ['exception' => $exception]);
            return PaymentResult::failed('The PayPal payment could not be completed.', $paypalOrderId);
        }
        if (!$capture->isCompleted()) {
            return PaymentResult::failed(sprintf('PayPal capture returned status "%s".', $capture->status), $paypalOrderId);
        }
        if (!$this->belongsToOrder($capture, $order)) {
            $this->logger->warning('PayPal capture does not match the order it was returned for.', [
                'order' => $order->getUid(),
                'paypalOrder' => $paypalOrderId,
            ]);
            return PaymentResult::failed('The PayPal payment did not match the order.', $this->externalId($capture, $paypalOrderId));
        }

        return PaymentResult::completed(PaymentStatus::PAID, $this->externalId($capture, $paypalOrderId));
    }

    /**
     * A valid callback token only proves the caller owns *some* order of this shop; the PayPal order id on
     * the return URL is a separate, caller-controlled value. Binding the capture to this order - by the
     * `custom_id` set at creation, the settled amount and the currency - stops a cheap captured PayPal order
     * from being replayed onto an expensive unpaid order.
     */
    private function belongsToOrder(PaypalCapture $capture, Order $order): bool
    {
        return $capture->customId === (string)($order->getUid() ?? 0)
            && $capture->amount !== null
            && $capture->amount->getCents() === $order->getTotalGross()->getCents()
            && strtoupper($capture->currency) === strtoupper($order->getCurrency());
    }

    /**
     * @param array<string, mixed> $event
     */
    private function interpretEvent(array $event): PaymentResult
    {
        $type = (string)($event['event_type'] ?? '');
        $resourceId = (string)($event['resource']['id'] ?? '');

        return match ($type) {
            'PAYMENT.CAPTURE.COMPLETED' => PaymentResult::completed(PaymentStatus::PAID, $resourceId, $event),
            'CHECKOUT.ORDER.APPROVED' => PaymentResult::pending($resourceId),
            default => PaymentResult::pending(),
        };
    }

    private function externalId(PaypalCapture $capture, string $paypalOrderId): string
    {
        return $capture->captureId !== '' ? $capture->captureId : $paypalOrderId;
    }
}
