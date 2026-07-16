<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface;
use GoldeneZeiten\Products\Payment\Amazon\Client\AmazonPayClient;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfiguration;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfigurationFactory;
use GoldeneZeiten\Products\Payment\Amazon\Domain\Dto\AmazonCheckoutSession;
use GoldeneZeiten\Products\Payment\Amazon\Event\ModifyAmazonCheckoutSessionRequestEvent;
use GoldeneZeiten\Products\Payment\Amazon\Exception\AmazonPayApiException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Amazon Pay (Checkout v2) as a redirect payment method. Amazon returns the customer twice: first to a
 * review URL, where the session is updated with the final amount to obtain the second redirect back to
 * Amazon, then to a result URL, where the charge is completed. The two legs are told apart by a `leg`
 * marker the method appends to its own return URLs; the core return middleware honours the intermediate
 * redirect so the order is only finalized on the second return {@see RedirectPaymentMethodInterface}.
 *
 * The amount is known before payment (the shop collects address and shipping first), so - unlike an
 * express integration - the buyer's Amazon address is not needed and the charge amount is the order total.
 */
final class AmazonPayPaymentMethod implements RedirectPaymentMethodInterface
{
    public const IDENTIFIER = 'amazon';

    private const SUPPORTED_CURRENCIES = [
        'EUR', 'GBP', 'USD', 'JPY', 'DKK', 'SEK', 'NOK', 'CHF', 'AUD', 'HKD', 'NZD', 'ZAR',
    ];

    public function __construct(
        private readonly AmazonPayConfigurationFactory $configurationFactory,
        private readonly AmazonPayClient $client,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_amazon', 'ProductsPaymentAmazon');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return $this->configurationFactory->forCurrentRequest()->isComplete()
            && in_array(strtoupper($context->getCurrency()), self::SUPPORTED_CURRENCIES, true);
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        if ($context->getReturnUrl() === '' || $context->getCancelUrl() === '') {
            return PaymentResult::failed('Amazon Pay needs a configured checkout page for its return URLs.');
        }
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $session = $this->client->createCheckoutSession(
                $this->createPayload($order, $context, $configuration),
                'create-' . $order->getOrderNumber(),
                $configuration
            );
        } catch (AmazonPayApiException $exception) {
            $this->logger->error('Amazon Pay session creation failed.', ['exception' => $exception]);
            return PaymentResult::failed('Amazon Pay session could not be created: ' . $exception->getMessage());
        }

        return PaymentResult::redirectRequired($this->buyerSignInUrl($session, $configuration), $session->checkoutSessionId);
    }

    public function handleReturn(ServerRequestInterface $request, Order $order): PaymentResult
    {
        $queryParams = $request->getQueryParams();
        $sessionId = (string)($queryParams['amazonCheckoutSessionId'] ?? '');
        if ($sessionId === '') {
            return PaymentResult::failed('Amazon Pay did not return a checkout session id on the return URL.');
        }
        if (($queryParams['leg'] ?? '') === 'result') {
            return $this->completeLeg($sessionId, $order);
        }

        return $this->reviewLeg($sessionId, $order, $request);
    }

    public function handleWebhook(ServerRequestInterface $request, Order $order): PaymentResult
    {
        // Amazon Pay settles synchronously on the result return, so the account-level SNS IPN is not relied
        // on here (it carries no session id and cannot produce this per-order signature). When a session id
        // is supplied, the session is re-read and honoured; otherwise the payment stays pending.
        $sessionId = $this->webhookSessionId($request);
        if ($sessionId === '') {
            return PaymentResult::pending();
        }
        try {
            $session = $this->client->getCheckoutSession($sessionId, $this->configurationFactory->forCurrentRequest());
        } catch (AmazonPayApiException $exception) {
            $this->logger->error('Amazon Pay webhook verification failed.', ['exception' => $exception]);
            return PaymentResult::failed('Amazon Pay webhook could not be verified: ' . $exception->getMessage());
        }

        return $session->isCompleted() ? PaymentResult::completed(PaymentStatus::PAID, $session->chargeId) : PaymentResult::pending();
    }

    private function reviewLeg(string $sessionId, Order $order, ServerRequestInterface $request): PaymentResult
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        try {
            $session = $this->client->getCheckoutSession($sessionId, $configuration);
            if (!$session->isOpen()) {
                return PaymentResult::failed('Amazon Pay session is not open for review.', $sessionId);
            }
            $updated = $this->client->updateCheckoutSession($sessionId, $this->updatePayload($order, $request), $configuration);
        } catch (AmazonPayApiException $exception) {
            $this->logger->error('Amazon Pay session update failed.', ['exception' => $exception]);
            return PaymentResult::failed('Amazon Pay session could not be updated: ' . $exception->getMessage());
        }
        if ($updated->amazonPayRedirectUrl === '') {
            return PaymentResult::failed('Amazon Pay returned no redirect URL after the update.', $sessionId);
        }

        return PaymentResult::redirectRequired($updated->amazonPayRedirectUrl, $sessionId);
    }

    private function completeLeg(string $sessionId, Order $order): PaymentResult
    {
        if ($order->getPaymentStatus() === PaymentStatus::PAID) {
            return PaymentResult::completed(PaymentStatus::PAID);
        }
        $configuration = $this->configurationFactory->forCurrentRequest();
        $payload = ['chargeAmount' => $this->chargeAmount($order->getTotalGross()->getCents(), $order->getCurrency())];
        try {
            $session = $this->client->completeCheckoutSession($sessionId, $payload, 'complete-' . $order->getOrderNumber(), $configuration);
        } catch (AmazonPayApiException $exception) {
            $this->logger->error('Amazon Pay completion failed.', ['exception' => $exception]);
            return PaymentResult::failed('Amazon Pay payment could not be completed: ' . $exception->getMessage());
        }

        return $this->interpret($session);
    }

    private function interpret(AmazonCheckoutSession $session): PaymentResult
    {
        if ($session->isCompleted()) {
            return PaymentResult::completed(PaymentStatus::PAID, $session->chargeId);
        }
        if ($session->isCanceled()) {
            return PaymentResult::failed('Amazon Pay canceled the checkout: ' . ($session->reasonCode !== '' ? $session->reasonCode : 'unknown'), $session->checkoutSessionId);
        }

        return PaymentResult::pending($session->chargeId);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(Order $order, PaymentContext $context, AmazonPayConfiguration $configuration): array
    {
        $payload = [
            'webCheckoutDetails' => [
                'checkoutReviewReturnUrl' => $this->withLeg($context->getReturnUrl(), 'review'),
                'checkoutResultReturnUrl' => $this->withLeg($context->getReturnUrl(), 'result'),
                'checkoutCancelUrl' => $context->getCancelUrl(),
            ],
            'storeId' => $configuration->storeId,
            'chargePermissionType' => 'OneTime',
            'paymentDetails' => [
                'paymentIntent' => 'AuthorizeWithCapture',
                'canHandlePendingAuthorization' => false,
                'chargeAmount' => $this->chargeAmount($context->getAmount()->getCents(), $context->getCurrency()),
            ],
            'merchantMetadata' => [
                'merchantReferenceId' => $order->getOrderNumber(),
                'merchantStoreName' => $configuration->merchantStoreName,
            ],
        ];
        $event = new ModifyAmazonCheckoutSessionRequestEvent($payload, $order, $context, $configuration);
        $this->eventDispatcher->dispatch($event);

        return $event->getPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(Order $order, ServerRequestInterface $request): array
    {
        return [
            'webCheckoutDetails' => [
                'checkoutResultReturnUrl' => $this->resultReturnUrl($request),
            ],
            'paymentDetails' => [
                'paymentIntent' => 'AuthorizeWithCapture',
                'canHandlePendingAuthorization' => false,
                'chargeAmount' => $this->chargeAmount($order->getTotalGross()->getCents(), $order->getCurrency()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function chargeAmount(int $cents, string $currency): array
    {
        return [
            'amount' => number_format($cents / 100, 2, '.', ''),
            'currencyCode' => strtoupper($currency),
        ];
    }

    private function buyerSignInUrl(AmazonCheckoutSession $session, AmazonPayConfiguration $configuration): string
    {
        return sprintf(
            'https://%s/checkout?amazonCheckoutSessionId=%s',
            $configuration->region->checkoutHost(),
            rawurlencode($session->checkoutSessionId)
        );
    }

    private function withLeg(string $url, string $leg): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'leg=' . $leg;
    }

    /**
     * The result return URL is the very URL this review request arrived on, with its leg flipped - so it
     * carries the same signed order token and resolves back to this method's completion leg.
     */
    private function resultReturnUrl(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $query = [];
        parse_str($uri->getQuery(), $query);
        $query['leg'] = 'result';

        return (string)$uri->withQuery(http_build_query($query));
    }

    private function webhookSessionId(ServerRequestInterface $request): string
    {
        $fromQuery = (string)($request->getQueryParams()['amazonCheckoutSessionId'] ?? '');
        if ($fromQuery !== '') {
            return $fromQuery;
        }
        $body = json_decode((string)$request->getBody(), true);

        return is_array($body) ? (string)($body['checkoutSessionId'] ?? '') : '';
    }
}
