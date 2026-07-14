<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Configuration\CreditPointsConfigurationFactory;
use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Payment\Exception\PaymentCallbackException;
use GoldeneZeiten\Products\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Payment\PaymentCallbackService;
use GoldeneZeiten\Products\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Checkout\CheckoutService;
use GoldeneZeiten\Products\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementExceptionInterface;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class CheckoutController extends ActionController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly PaymentCallbackService $paymentCallbackService,
        private readonly PaymentContextFactory $paymentContextFactory,
        private readonly OrderPlacementService $orderPlacementService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly CreditPointsService $creditPointsService,
        private readonly ShippingCostService $shippingCostService,
        private readonly InvoiceTokenService $invoiceTokenService,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly OrderTokenService $orderTokenService,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly CreditPointsConfigurationFactory $creditPointsConfigurationFactory,
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
        $address = $this->checkoutService->getAddress($this->request);
        $this->view->assignMultiple([
            'shippingMethods' => $this->shippingCostService->resolveAvailable($configuration, $this->checkoutService->getBasketViewModel($this->request), $address->getCountry()),
            'selectedShippingMethod' => $this->checkoutService->getShippingMethod($this->request),
        ]);
        return $this->htmlResponse();
    }

    public function submitShippingMethodAction(int $shippingMethod): ResponseInterface
    {
        $this->checkoutService->setShippingMethod($this->request, $shippingMethod);
        return $this->redirect('payment');
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
        $shippingMethodUid = $this->checkoutService->getShippingMethod($this->request);

        $this->view->assignMultiple([
            'basket' => $basket,
            'address' => $address,
            'paymentMethod' => $this->findPaymentMethod($paymentMethodIdentifier),
            'creditPointsBalance' => $this->creditPointsBalance(),
            'shippingMethod' => $this->shippingCostService->findMethod($shippingMethodUid),
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
        $configuration = $this->creditPointsConfigurationFactory->create($this->request);
        if (!$configuration->isEnabled()) {
            return 0;
        }
        return $this->creditPointsService->getBalance($this->frontendUserResolver->getUid($this->request));
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
            $this->checkoutService->getShippingMethod($this->request),
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
