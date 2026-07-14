<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Loyalty\LoyaltyRegistry;
use GoldeneZeiten\Products\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Payment\PaymentCallbackService;
use GoldeneZeiten\Products\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Checkout\CheckoutService;
use GoldeneZeiten\Products\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementExceptionInterface;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Shipping\ShippingContextFactory;
use GoldeneZeiten\Products\Shipping\ShippingQuoteService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class CheckoutController extends ActionController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly PaymentCallbackService $paymentCallbackService,
        private readonly LoyaltyRegistry $loyaltyRegistry,
        private readonly PaymentContextFactory $paymentContextFactory,
        private readonly OrderPlacementService $orderPlacementService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ShippingQuoteService $shippingQuoteService,
        private readonly ShippingContextFactory $shippingContextFactory,
        private readonly InvoiceTokenService $invoiceTokenService,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly OrderTokenService $orderTokenService,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly PriceQuoteService $priceQuoteService
    ) {}

    public function addressAction(): ResponseInterface
    {
        $deliveryAddress = $this->checkoutService->getDeliveryAddress($this->request);
        $this->view->assignMultiple([
            'address' => $this->checkoutService->getAddress($this->request),
            'deliveryAddress' => $deliveryAddress ?? new Address(),
            'shipToDifferentAddress' => $deliveryAddress !== null,
            'giftMessage' => $this->checkoutService->getGiftMessage($this->request),
        ]);
        return $this->htmlResponse();
    }

    public function submitAddressAction(Address $address, bool $shipToDifferentAddress = false, ?Address $deliveryAddress = null, string $giftMessage = ''): ResponseInterface
    {
        $this->checkoutService->setAddress($this->request, $address);
        $this->checkoutService->setDeliveryAddress($this->request, $shipToDifferentAddress ? $deliveryAddress : null);
        $this->checkoutService->setGiftMessage($this->request, $giftMessage);
        $configuration = $this->configurationFactory->create($this->request);
        return $this->redirect($configuration->isShippingEnabled() ? 'shippingMethod' : 'payment');
    }

    public function shippingMethodAction(): ResponseInterface
    {
        $configuration = $this->configurationFactory->create($this->request);
        if (!$configuration->isShippingEnabled()) {
            return $this->redirect('payment');
        }
        $options = $this->shippingQuoteService->getAvailableOptions($configuration, $this->shippingContext());
        if ($options === []) {
            $this->addFlashMessage($this->translate('checkout_no_shipping_available'), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('address');
        }
        $this->view->assignMultiple([
            'shippingOptions' => $options,
            'selectedShippingOption' => $this->selectedShippingOption($options),
        ]);
        return $this->htmlResponse();
    }

    public function submitShippingMethodAction(string $shippingOption): ResponseInterface
    {
        $this->checkoutService->setShippingOption($this->request, $shippingOption);
        return $this->redirect('payment');
    }

    private function shippingContext(): ShippingContext
    {
        return $this->shippingContextFactory->createFromBasket(
            $this->checkoutService->getBasketViewModel($this->request),
            $this->checkoutService->getAddress($this->request),
            $this->frontendUserResolver->getUid($this->request)
        );
    }

    /**
     * Which option starts out selected is the shop's policy, not a carrier's: the cheapest, a named
     * default, or none at all.
     *
     * @param ShippingOption[] $options
     */
    private function selectedShippingOption(array $options): string
    {
        $chosen = $this->checkoutService->getShippingOption($this->request);
        if ($chosen !== '') {
            return $chosen;
        }
        $preselect = (string)($this->request->getAttribute('site')?->getSettings()->get('products.shipping.preselect', 'none') ?? 'none');
        if ($preselect === 'cheapest') {
            return $this->cheapestOption($options);
        }
        return $preselect === 'none' ? '' : $preselect;
    }

    /**
     * @param ShippingOption[] $options
     */
    private function cheapestOption(array $options): string
    {
        $cheapest = null;
        foreach ($options as $option) {
            if ($cheapest === null || $option->getCost()->getCents() < $cheapest->getCost()->getCents()) {
                $cheapest = $option;
            }
        }
        return $cheapest?->getKey() ?? '';
    }

    private function translate(string $key): string
    {
        return (string)LocalizationUtility::translate($key, 'Products');
    }

    public function paymentAction(): ResponseInterface
    {
        $paymentMethod = $this->checkoutService->getPaymentMethod($this->request);
        $this->view->assign('paymentMethod', $paymentMethod);
        $this->view->assign('paymentMethods', $this->paymentMethodRegistry->getAvailable($this->buildPaymentContext()));
        return $this->htmlResponse();
    }

    public function submitPaymentAction(string $paymentMethod): ResponseInterface
    {
        $this->checkoutService->setPaymentMethod($this->request, $paymentMethod);
        return $this->redirect('review');
    }

    public function reviewAction(): ResponseInterface
    {
        $basket = $this->checkoutService->getBasketViewModel($this->request);
        $this->priceQuoteService->freeze($this->request, $basket);
        $address = $this->checkoutService->getAddress($this->request);
        $paymentMethodIdentifier = $this->checkoutService->getPaymentMethod($this->request);
        $shippingOptionKey = $this->checkoutService->getShippingOption($this->request);

        $this->view->assignMultiple([
            'basket' => $basket,
            'address' => $address,
            'paymentMethod' => $this->findPaymentMethod($paymentMethodIdentifier),
            'creditPointsBalance' => $this->creditPointsBalance(),
            'shippingSelection' => $this->shippingQuoteService->resolveSelection($this->configurationFactory->create($this->request), $this->shippingContext(), $shippingOptionKey, $this->request),
            'deliveryAddress' => $this->checkoutService->getDeliveryAddress($this->request),
            'giftMessage' => $this->checkoutService->getGiftMessage($this->request),
        ]);
        return $this->htmlResponse();
    }

    /**
     * 0 hides the spend-points input and indicates nothing to spend.
     */
    private function creditPointsBalance(): int
    {
        return $this->loyaltyRegistry->getBalance($this->loyaltyContext());
    }

    private function loyaltyContext(): LoyaltyContext
    {
        $basket = $this->checkoutService->getBasketViewModel($this->request);

        return new LoyaltyContext(
            $this->request,
            $basket,
            $basket->getTotalGross(),
            $this->frontendUserResolver->getUid($this->request)
        );
    }

    private function findPaymentMethod(string $identifier): ?PaymentMethodInterface
    {
        if ($identifier === '') {
            return null;
        }

        try {
            return $this->paymentMethodRegistry->get($identifier);
        } catch (PaymentMethodNotFoundException) {
            return null;
        }
    }

    public function finalizeAction(int $spendPoints = 0, bool $termsAccepted = false): ResponseInterface
    {
        $address = $this->checkoutService->getAddress($this->request);
        $paymentMethodIdentifier = $this->checkoutService->getPaymentMethod($this->request);
        $choices = new CheckoutChoices(
            $spendPoints,
            $this->checkoutService->getShippingOption($this->request),
            $this->checkoutService->getDeliveryAddress($this->request),
            $this->checkoutService->getGiftMessage($this->request),
            $termsAccepted
        );

        try {
            $result = $this->orderPlacementService->place($this->request, $address, $paymentMethodIdentifier, $choices);
        } catch (OrderPlacementExceptionInterface $exception) {
            $this->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('review');
        }

        if ($result->requiresRedirect()) {
            return new RedirectResponse($result->getRedirectUrl());
        }

        return $this->redirect('thankYou', null, null, ['order' => $result->getOrder()->getUid()]);
    }

    /**
     * Where a redirect payment method sends the customer back to. The gateway decides the outcome, not
     * this request - the payment method verifies it before the order is finalized.
     */
    public function paymentReturnAction(int $order = 0, string $token = ''): ResponseInterface
    {
        try {
            $placedOrder = $this->paymentCallbackService->handleReturn($order, $token, $this->request);
        } catch (PaymentCallbackException|OrderPlacementExceptionInterface $exception) {
            $this->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('review');
        }

        return $this->redirect('thankYou', null, null, ['order' => $placedOrder->getUid()]);
    }

    /**
     * Where a redirect payment method sends the customer that abandoned the payment. The order stays as
     * it is, so they can pick another method and try again.
     */
    public function paymentCancelAction(int $order = 0, string $token = ''): ResponseInterface
    {
        try {
            $this->paymentCallbackService->resolveOrder($order, $token);
        } catch (PaymentCallbackException $exception) {
            $this->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
        }

        return $this->redirect('payment');
    }

    public function thankYouAction(int $order): ResponseInterface
    {
        $orderObject = $this->checkoutService->getOrder($order);
        if ($orderObject === null) {
            return $this->redirect('list', 'Product');
        }
        $this->view->assignMultiple([
            'order' => $orderObject,
            'invoiceHash' => $this->invoiceTokenService->generateToken($orderObject),
            'withdrawalHash' => $this->withdrawalTokenService->generateToken($orderObject),
            'orderHash' => $this->orderTokenService->generateToken($orderObject),
        ]);
        return $this->htmlResponse();
    }

    private function buildPaymentContext(): PaymentContext
    {
        $address = $this->checkoutService->getAddress($this->request);
        $basket = $this->checkoutService->getBasketViewModel($this->request);
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        return $this->paymentContextFactory->createFromBasket($basket, $address, $frontendUserUid);
    }
}
