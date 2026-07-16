<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Express;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Express\ExpressOrderService;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Shipping\ShippingContextFactory;
use GoldeneZeiten\Products\Core\Shipping\ShippingQuoteService;
use GoldeneZeiten\Products\Express\Stripe\Configuration\StripeExpressConfigurationFactory;
use GoldeneZeiten\Products\Express\Stripe\Express\Exception\ExpressPaymentDeclinedException;
use GoldeneZeiten\Products\Express\Stripe\Payment\StripeExpressPaymentClient;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Orchestrates the Stripe express confirm: recompute the amount from the shop's own basket and shipping -
 * never the client's - settle it as a PaymentIntent, and only on success create the order. Because the
 * charge is settled before the order exists, the amount is computed here the same way the shipping-rate
 * callback quoted it (goods total plus the chosen carrier cost), so the buyer is charged what the wallet
 * sheet showed. Order creation then reruns the identical quote, keeping the two in step.
 */
#[Autoconfigure(public: true)]
final readonly class StripeExpressConfirmService
{
    public function __construct(
        private StripeExpressConfigurationFactory $configurationFactory,
        private StripeExpressPaymentClient $paymentClient,
        private ExpressOrderService $orderService,
        private ExpressCheckoutProviderRegistry $providerRegistry,
        private ShippingQuoteService $shippingQuoteService,
        private ShippingContextFactory $shippingContextFactory,
        private PriceQuoteService $priceQuoteService,
        private ProductsConfigurationFactory $productsConfigurationFactory
    ) {}

    public function confirm(
        ServerRequestInterface $request,
        BasketViewModel $liveBasketViewModel,
        Address $address,
        string $shippingOptionKey,
        string $paymentMethodId
    ): Order {
        $productsConfiguration = $this->productsConfigurationFactory->create($request);
        $quotedBasket = $this->priceQuoteService->resolve($request, $liveBasketViewModel, $productsConfiguration);
        $amount = $quotedBasket->getTotalGross()->getCents()
            + $this->shippingCents($productsConfiguration, $quotedBasket, $address, $shippingOptionKey);

        $paymentResult = $this->paymentClient->settle(
            $amount,
            $quotedBasket->getCurrency(),
            $paymentMethodId,
            $this->configurationFactory->forCurrentRequest()
        );
        if ($paymentResult->getState() !== PaymentResultState::COMPLETED) {
            throw new ExpressPaymentDeclinedException(
                sprintf('Stripe express payment was not settled: %s', $paymentResult->getFailureReason()),
                1784220771
            );
        }

        return $this->orderService->place(
            $request,
            $this->providerRegistry->get(StripeExpressCheckoutProvider::IDENTIFIER),
            $liveBasketViewModel,
            $address,
            $shippingOptionKey,
            $paymentResult
        );
    }

    private function shippingCents(ProductsConfiguration $configuration, BasketViewModel $basket, Address $address, string $shippingOptionKey): int
    {
        if ($shippingOptionKey === '') {
            return 0;
        }
        $context = $this->shippingContextFactory->createFromBasket($basket, $address);
        foreach ($this->shippingQuoteService->getAvailableOptions($configuration, $context) as $option) {
            if ($option->getKey() === $shippingOptionKey) {
                return $option->getCost()->getCents();
            }
        }

        return 0;
    }
}
