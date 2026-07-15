<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
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
final class PaypalPaymentMethod implements RedirectPaymentMethodInterface
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
            return PaymentResult::failed('PayPal order could not be created: ' . $exception->getMessage());
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

        return $this->capture($paypalOrderId);
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

    private function capture(string $paypalOrderId): PaymentResult
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $capture = $this->orderClient->capture($paypalOrderId, $configuration);
        } catch (PaypalApiException $exception) {
            $this->logger->error('PayPal capture failed.', ['exception' => $exception]);
            return PaymentResult::failed('PayPal capture failed: ' . $exception->getMessage(), $paypalOrderId);
        }
        if (!$capture->isCompleted()) {
            return PaymentResult::failed(sprintf('PayPal capture returned status "%s".', $capture->status), $paypalOrderId);
        }

        return PaymentResult::completed(PaymentStatus::PAID, $this->externalId($capture, $paypalOrderId));
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
