<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Express;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Express\ExpressOrderService;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Shipping\ShippingContextFactory;
use GoldeneZeiten\Products\Core\Shipping\ShippingQuoteService;
use GoldeneZeiten\Products\Express\GooglePay\Configuration\GooglePayConfigurationFactory;
use GoldeneZeiten\Products\Express\GooglePay\Express\Exception\ExpressPaymentDeclinedException;
use GoldeneZeiten\Products\Express\GooglePay\Payment\Exception\GooglePayProcessorException;
use GoldeneZeiten\Products\Express\GooglePay\Payment\GooglePayAuthorization;
use GoldeneZeiten\Products\Express\GooglePay\Payment\GooglePayProcessorClient;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Orchestrates the one server step of a Google Pay express checkout: once the buyer has authorized in the
 * Google Pay sheet, recompute the amount from the shop's own basket and shipping, authorize the token
 * through the shop's processor, and only on approval create the paid order through the same
 * {@see ExpressOrderService} normal checkout uses.
 *
 * Live shipping needs no step here: the Google Pay sheet's `onPaymentDataChanged` callback is answered
 * client-side from the core shipping-quote endpoint, exactly as the Stripe express provider does.
 */
#[Autoconfigure(public: true)]
final readonly class GooglePayExpressCheckoutService
{
    public function __construct(
        private GooglePayConfigurationFactory $configurationFactory,
        private GooglePayProcessorClient $processorClient,
        private ExpressOrderService $orderService,
        private ExpressCheckoutProviderRegistry $providerRegistry,
        private ShippingQuoteService $shippingQuoteService,
        private ShippingContextFactory $shippingContextFactory,
        private PriceQuoteService $priceQuoteService,
        private ProductsConfigurationFactory $productsConfigurationFactory
    ) {}

    public function confirm(ServerRequestInterface $request, BasketViewModel $liveBasketViewModel, Address $address, string $shippingOptionKey, string $token): Order
    {
        $productsConfiguration = $this->productsConfigurationFactory->create($request);
        $quotedBasket = $this->priceQuoteService->resolve($request, $liveBasketViewModel, $productsConfiguration);
        $amount = $quotedBasket->getTotalGross()->getCents()
            + $this->shippingCents($productsConfiguration, $quotedBasket, $address, $shippingOptionKey);
        $authorization = $this->authorize($token, $amount, $quotedBasket->getCurrency());

        return $this->orderService->place(
            $request,
            $this->providerRegistry->get(GooglePayExpressCheckoutProvider::IDENTIFIER),
            $liveBasketViewModel,
            $address,
            $shippingOptionKey,
            PaymentResult::completed(PaymentStatus::PAID, $authorization->getTransactionId())
        );
    }

    private function authorize(string $token, int $amountCents, string $currency): GooglePayAuthorization
    {
        try {
            $authorization = $this->processorClient->authorize($token, $amountCents, $currency, $this->configurationFactory->forCurrentRequest());
        } catch (GooglePayProcessorException $exception) {
            throw new ExpressPaymentDeclinedException('Google Pay settlement failed: ' . $exception->getMessage(), 1784220863, $exception);
        }
        if (!$authorization->isApproved()) {
            throw new ExpressPaymentDeclinedException(sprintf('Google Pay was not approved (status "%s").', $authorization->getStatus()), 1784220862);
        }

        return $authorization;
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
