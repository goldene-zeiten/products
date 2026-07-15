<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use GoldeneZeiten\Products\Payment\Klarna\Client\KlarnaClient;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfiguration;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfigurationFactory;
use GoldeneZeiten\Products\Payment\Klarna\Domain\Dto\KlarnaOrder;
use GoldeneZeiten\Products\Payment\Klarna\Event\ModifyKlarnaSessionRequestEvent;
use GoldeneZeiten\Products\Payment\Klarna\Exception\KlarnaApiException;
use GoldeneZeiten\Products\Payment\Klarna\Order\KlarnaOrderPayloadBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Klarna Hosted Payment Page as a redirect payment method: the customer is sent to Klarna's hosted pages
 * to authorize, returns to the shop where the order is placed server-to-server, and Klarna's status
 * callback confirms the same session. Klarna does not sign its callback, so the webhook is verified by
 * re-reading the session from Klarna with the shop's own credentials. Placing the order guards on the
 * order's paid state, so a return after payment or a replayed callback is a no-op.
 */
final class KlarnaPaymentMethod implements RedirectPaymentMethodInterface
{
    public const IDENTIFIER = 'klarna';

    /**
     * The currencies Klarna settles in. Offering Klarna for anything else only fails when the session opens.
     */
    private const SUPPORTED_CURRENCIES = [
        'EUR', 'SEK', 'NOK', 'DKK', 'GBP', 'USD', 'CHF', 'PLN', 'CZK', 'AUD', 'CAD', 'NZD', 'RON',
    ];

    public function __construct(
        private readonly KlarnaConfigurationFactory $configurationFactory,
        private readonly KlarnaClient $client,
        private readonly KlarnaOrderPayloadBuilder $orderPayloadBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_klarna', 'ProductsPaymentKlarna');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return $this->configurationFactory->forCurrentRequest()->isComplete()
            && in_array(strtoupper($context->getCurrency()), self::SUPPORTED_CURRENCIES, true);
    }

    public function getPriority(): int
    {
        return 40;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        if ($context->getReturnUrl() === '' || $context->getCancelUrl() === '') {
            return PaymentResult::failed('Klarna needs a configured checkout page for its return URLs.');
        }
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $sessionId = $this->client->createPaymentSession($this->sessionPayload($order, $context, $configuration), $configuration);
            $hppSession = $this->client->createHppSession($sessionId, $this->merchantUrls($context), $configuration);
        } catch (KlarnaApiException $exception) {
            $this->logger->error('Klarna session creation failed.', ['exception' => $exception]);
            return PaymentResult::failed('Klarna session could not be created: ' . $exception->getMessage());
        }
        if ($hppSession->redirectUrl === '') {
            return PaymentResult::failed('Klarna returned no redirect URL.', $hppSession->hppSessionId);
        }

        return PaymentResult::redirectRequired($hppSession->redirectUrl, $hppSession->hppSessionId);
    }

    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
    {
        // Klarna substitutes the authorization token into the success URL's placeholder; the shop's own
        // signed token lives under the checkout plugin namespace, so the two never collide.
        $authorizationToken = (string)($request->getQueryParams()['authorization_token'] ?? '');
        if ($authorizationToken === '') {
            return PaymentResult::failed('Klarna did not return an authorization token on the return URL.');
        }

        return $this->placeOrder($authorizationToken, $order);
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        $event = json_decode((string)$request->getBody(), true);
        $sessionId = is_array($event) ? (string)($event['session']['session_id'] ?? '') : '';
        if ($sessionId === '') {
            return PaymentResult::failed('Klarna webhook carried no session id.');
        }

        return $this->finalizeFromWebhook($sessionId, $order);
    }

    private function finalizeFromWebhook(string $sessionId, Order $order): PaymentResult
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            // Klarna does not sign its callback, so an authenticated re-read is the verification: only the
            // shop's credentials can make Klarna report the real status for this session.
            $status = $this->client->readHppSession($sessionId, $configuration);
        } catch (KlarnaApiException $exception) {
            $this->logger->error('Klarna session verification failed.', ['exception' => $exception]);
            return PaymentResult::failed('Klarna webhook could not be verified: ' . $exception->getMessage());
        }
        if (!$status->isCompleted() || $status->authorizationToken === '') {
            return PaymentResult::pending();
        }

        return $this->placeOrder($status->authorizationToken, $order);
    }

    private function placeOrder(string $authorizationToken, Order $order): PaymentResult
    {
        if ($order->getPaymentStatus() === PaymentStatus::PAID) {
            return PaymentResult::completed(PaymentStatus::PAID);
        }
        $configuration = $this->configurationFactory->forCurrentRequest();
        $payload = $this->orderPayloadBuilder->build(
            $order->getTotalGross()->getCents(),
            $order->getCurrency(),
            $order->getTaxCountry(),
            $order->getOrderNumber(),
        ) + ['merchant_reference1' => $order->getOrderNumber()];
        try {
            $klarnaOrder = $this->client->placeOrder($authorizationToken, $payload, $configuration);
        } catch (KlarnaApiException $exception) {
            $this->logger->error('Klarna order placement failed.', ['exception' => $exception]);
            return PaymentResult::failed('Klarna order placement failed: ' . $exception->getMessage());
        }

        return $this->interpret($klarnaOrder);
    }

    private function interpret(KlarnaOrder $klarnaOrder): PaymentResult
    {
        if ($klarnaOrder->isAccepted()) {
            return PaymentResult::completed(PaymentStatus::PAID, $klarnaOrder->orderId);
        }
        if ($klarnaOrder->isPending()) {
            return PaymentResult::pending($klarnaOrder->orderId);
        }

        return PaymentResult::failed('Klarna refused the payment.', $klarnaOrder->orderId);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(Order $order, PaymentContext $context, KlarnaConfiguration $configuration): array
    {
        $payload = $this->orderPayloadBuilder->build(
            $context->getAmount()->getCents(),
            $context->getCurrency(),
            $context->getCountryCode(),
            $order->getOrderNumber(),
        ) + ['intent' => 'buy'];
        $event = new ModifyKlarnaSessionRequestEvent($payload, $order, $context, $configuration);
        $this->eventDispatcher->dispatch($event);

        return $event->getPayload();
    }

    /**
     * @return array<string, string>
     */
    private function merchantUrls(PaymentContext $context): array
    {
        $returnUrl = $context->getReturnUrl();
        $success = $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'authorization_token={{authorization_token}}';

        return [
            'success' => $success,
            'cancel' => $context->getCancelUrl(),
            'back' => $context->getCancelUrl(),
            'failure' => $context->getCancelUrl(),
            'error' => $context->getCancelUrl(),
            'status_update' => $context->getWebhookUrl(),
        ];
    }
}
