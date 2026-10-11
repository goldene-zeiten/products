<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Express\ExpressSettlementInterface;
use GoldeneZeiten\Products\Express\Stripe\Configuration\StripeExpressConfiguration;
use GoldeneZeiten\Products\Express\Stripe\Express\Exception\ExpressPaymentDeclinedException;
use GoldeneZeiten\Products\Express\Stripe\Payment\StripeExpressPaymentClient;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One Stripe wallet authorization, handed to the core express order service by
 * {@see StripeExpressConfirmService}: it settles the PaymentIntent for the amount core asks for and
 * refunds it when the order that amount was meant to pay cannot be placed. A PaymentIntent that is still
 * processing is accepted as pending; Stripe's webhook settles it later.
 */
#[Exclude]
final readonly class StripeExpressSettlement implements ExpressSettlementInterface
{
    public function __construct(
        private StripeExpressPaymentClient $paymentClient,
        private StripeExpressConfiguration $configuration,
        private string $currency,
        private string $paymentMethodId
    ) {}

    public function settle(Order $order): PaymentResult
    {
        $result = $this->paymentClient->settle($order->getTotalGross()->getCents(), $this->currency, $this->paymentMethodId, $this->configuration);
        if ($result->getState() !== PaymentResultState::COMPLETED && $result->getState() !== PaymentResultState::PENDING) {
            throw new ExpressPaymentDeclinedException(
                sprintf('Stripe express payment was not settled: %s', $result->getFailureReason()),
                1784220771
            );
        }

        return $result;
    }

    public function refund(PaymentResult $settled): void
    {
        $this->paymentClient->refund($settled->getExternalId(), $this->configuration);
    }
}
