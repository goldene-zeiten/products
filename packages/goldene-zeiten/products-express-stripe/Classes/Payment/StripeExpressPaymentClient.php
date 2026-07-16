<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Express\Stripe\Configuration\StripeExpressConfiguration;
use GoldeneZeiten\Products\Payment\Stripe\Client\StripeClientFactory;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

/**
 * Settles an express wallet payment by creating and confirming a Stripe PaymentIntent in one call for the
 * server-computed amount and the payment method the Express Checkout Element authorised. Redirects are
 * disabled: a wallet payment resolves in the sheet, so a "succeeded" intent means paid and anything else
 * is treated as not-yet-paid rather than sending the buyer off to a redirect the express flow has no
 * place for.
 */
final class StripeExpressPaymentClient
{
    public function __construct(
        private readonly StripeClientFactory $clientFactory,
        private readonly LoggerInterface $logger
    ) {}

    public function settle(int $amountCents, string $currency, string $paymentMethodId, StripeExpressConfiguration $configuration): PaymentResult
    {
        try {
            $intent = $this->clientFactory->create($configuration->toStripeConfiguration())->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
            ]);
        } catch (ApiErrorException $exception) {
            $this->logger->error('Stripe express PaymentIntent failed.', ['exception' => $exception]);
            return PaymentResult::failed('Stripe express payment failed: ' . $exception->getMessage());
        }

        return $this->interpret($intent);
    }

    private function interpret(PaymentIntent $intent): PaymentResult
    {
        $status = (string)$intent->status;
        $externalId = (string)$intent->id;
        if ($status === 'succeeded') {
            return PaymentResult::completed(PaymentStatus::PAID, $externalId);
        }
        if ($status === 'processing') {
            return PaymentResult::pending($externalId);
        }

        return PaymentResult::failed(sprintf('Stripe express payment was not completed (status "%s").', $status), $externalId);
    }
}
