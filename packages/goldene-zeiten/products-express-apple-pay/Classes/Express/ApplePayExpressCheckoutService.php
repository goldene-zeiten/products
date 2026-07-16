<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Express;

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
use GoldeneZeiten\Products\Express\ApplePay\Configuration\ApplePayConfigurationFactory;
use GoldeneZeiten\Products\Express\ApplePay\Express\Exception\ExpressPaymentDeclinedException;
use GoldeneZeiten\Products\Express\ApplePay\Payment\ApplePayAuthorization;
use GoldeneZeiten\Products\Express\ApplePay\Payment\ApplePayProcessorClient;
use GoldeneZeiten\Products\Express\ApplePay\Payment\Exception\ApplePayProcessorException;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Orchestrates the two server steps of an Apple Pay express checkout: validate the merchant session the
 * sheet needs to open (the browser cannot, it requires the Apple Pay merchant certificate the processor
 * holds), and - once the buyer has authorized - recompute the amount from the shop's own basket and
 * shipping, authorize the encrypted token through the shop's processor, and only on approval create the
 * paid order through the same {@see ExpressOrderService} normal checkout uses.
 *
 * Live shipping needs no step here: the Apple Pay sheet's shipping callbacks are answered client-side from
 * the core shipping-quote endpoint, exactly as the Stripe express provider does.
 */
#[Autoconfigure(public: true)]
final readonly class ApplePayExpressCheckoutService
{
    public function __construct(
        private ApplePayConfigurationFactory $configurationFactory,
        private ApplePayProcessorClient $processorClient,
        private ExpressOrderService $orderService,
        private ExpressCheckoutProviderRegistry $providerRegistry,
        private ShippingQuoteService $shippingQuoteService,
        private ShippingContextFactory $shippingContextFactory,
        private PriceQuoteService $priceQuoteService,
        private ProductsConfigurationFactory $productsConfigurationFactory
    ) {}

    /**
     * @return array<string, mixed> the Apple merchant session to hand back to the sheet
     */
    public function validateMerchant(string $validationUrl, string $domainName): array
    {
        return $this->processorClient->validateMerchant($validationUrl, $domainName, $this->configurationFactory->forCurrentRequest());
    }

    /**
     * @param array<string, mixed> $token the ApplePayPaymentToken from the authorized payment
     */
    public function confirm(ServerRequestInterface $request, BasketViewModel $liveBasketViewModel, Address $address, string $shippingOptionKey, array $token): Order
    {
        $productsConfiguration = $this->productsConfigurationFactory->create($request);
        $quotedBasket = $this->priceQuoteService->resolve($request, $liveBasketViewModel, $productsConfiguration);
        $amount = $quotedBasket->getTotalGross()->getCents()
            + $this->shippingCents($productsConfiguration, $quotedBasket, $address, $shippingOptionKey);
        $authorization = $this->authorize($token, $amount, $quotedBasket->getCurrency());

        return $this->orderService->place(
            $request,
            $this->providerRegistry->get(ApplePayExpressCheckoutProvider::IDENTIFIER),
            $liveBasketViewModel,
            $address,
            $shippingOptionKey,
            PaymentResult::completed(PaymentStatus::PAID, $authorization->getTransactionId())
        );
    }

    /**
     * @param array<string, mixed> $token
     */
    private function authorize(array $token, int $amountCents, string $currency): ApplePayAuthorization
    {
        try {
            $authorization = $this->processorClient->authorize($token, $amountCents, $currency, $this->configurationFactory->forCurrentRequest());
        } catch (ApplePayProcessorException $exception) {
            throw new ExpressPaymentDeclinedException('Apple Pay settlement failed: ' . $exception->getMessage(), 1784220845, $exception);
        }
        if (!$authorization->isApproved()) {
            throw new ExpressPaymentDeclinedException(sprintf('Apple Pay was not approved (status "%s").', $authorization->getStatus()), 1784220846);
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
