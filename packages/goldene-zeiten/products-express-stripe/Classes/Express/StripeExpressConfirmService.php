<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Express\ExpressOrderService;
use GoldeneZeiten\Products\Express\Stripe\Configuration\StripeExpressConfigurationFactory;
use GoldeneZeiten\Products\Express\Stripe\Payment\StripeExpressPaymentClient;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Orchestrates the Stripe express confirm: hand the core express order service the wallet authorization as
 * a settlement. The service creates the order first and asks the settlement to charge exactly the order
 * total - never an amount the client or this package computed - so the buyer pays what the order says, and
 * a PaymentIntent whose order cannot be placed is refunded.
 */
#[Autoconfigure(public: true)]
final readonly class StripeExpressConfirmService
{
    public function __construct(
        private StripeExpressConfigurationFactory $configurationFactory,
        private StripeExpressPaymentClient $paymentClient,
        private ExpressOrderService $orderService,
        private ExpressCheckoutProviderRegistry $providerRegistry
    ) {}

    public function confirm(
        ServerRequestInterface $request,
        BasketViewModel $liveBasketViewModel,
        Address $address,
        string $shippingOptionKey,
        string $paymentMethodId
    ): Order {
        return $this->orderService->place(
            $request,
            $this->providerRegistry->get(StripeExpressCheckoutProvider::IDENTIFIER),
            $liveBasketViewModel,
            $address,
            $shippingOptionKey,
            new StripeExpressSettlement(
                $this->paymentClient,
                $this->configurationFactory->forRequest($request),
                $liveBasketViewModel->getCurrency(),
                $paymentMethodId
            )
        );
    }
}
