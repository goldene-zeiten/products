<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderTotals;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingSelection;
use GoldeneZeiten\Products\Core\Domain\Validator\AddressValidator;
use GoldeneZeiten\Products\Core\Event\EnrichCheckoutReviewEvent;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotAvailableException;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Core\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Checkout\AddressCompletenessChecker;
use GoldeneZeiten\Products\Core\Service\Checkout\CheckoutService;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\IncompleteAddressException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Core\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalExclusionService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Core\Shipping\Exception\MissingShippingOptionException;
use GoldeneZeiten\Products\Core\Shipping\Exception\NoShippingOptionAvailableException;
use GoldeneZeiten\Products\Core\Shipping\ShippingContextFactory;
use GoldeneZeiten\Products\Core\Shipping\ShippingQuoteService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;

final class CheckoutController extends ActionController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly PaymentContextFactory $paymentContextFactory,
        private readonly OrderPlacementService $orderPlacementService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ShippingQuoteService $shippingQuoteService,
        private readonly ShippingContextFactory $shippingContextFactory,
        private readonly InvoiceTokenService $invoiceTokenService,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly OrderTokenService $orderTokenService,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly PriceQuoteService $priceQuoteService,
        private readonly WithdrawalExclusionService $withdrawalExclusionService,
        private readonly AddressCompletenessChecker $addressCompletenessChecker,
        private readonly OrderCreationService $orderCreationService
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

    /**
     * Adds the billing address rules to the argument's validation, so an incomplete address re-renders the
     * address form with a message at each invalid field.
     */
    protected function initializeSubmitAddressAction(): void
    {
        $validator = $this->arguments->getArgument('address')->getValidator();
        $addressValidator = $this->validatorResolver->createValidator(AddressValidator::class, [], $this->request);
        if ($validator instanceof ConjunctionValidator && $addressValidator !== null) {
            $validator->addValidator($addressValidator);
        }
    }

    public function submitAddressAction(Address $address, bool $shipToDifferentAddress = false, ?Address $deliveryAddress = null, string $giftMessage = ''): ResponseInterface
    {
        if ($shipToDifferentAddress && !$this->addressCompletenessChecker->isComplete($deliveryAddress ?? new Address(), emailRequired: false)) {
            $this->backToStep('address', $this->translate('checkout_delivery_address_incomplete'));
        }
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

    public function submitShippingMethodAction(string $shippingOption = ''): ResponseInterface
    {
        $configuration = $this->configurationFactory->create($this->request);
        if (!$this->shippingQuoteService->isOffered($configuration, $this->shippingContext(), $shippingOption)) {
            $this->backToStep('shippingMethod', $this->translate('checkout_shipping_option_required'));
        }
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

    /**
     * Sends the customer back to a checkout step with an error message. The redirect is propagated as the
     * response of the whole request, as this plugin is not cached and a returned redirect would only be
     * sent as a header on a fully rendered page.
     */
    private function backToStep(string $action, string $message): never
    {
        $this->addFlashMessage($message, '', ContextualFeedbackSeverity::ERROR);
        throw new PropagateResponseException($this->redirect($action), 1791662865);
    }

    private function translate(string $key): string
    {
        return (string)LocalizationUtility::translate($key, 'ProductsCore');
    }

    public function paymentAction(): ResponseInterface
    {
        $paymentMethod = $this->checkoutService->getPaymentMethod($this->request);
        $this->view->assign('paymentMethod', $paymentMethod);
        $this->view->assign('paymentMethods', $this->paymentMethodRegistry->getAvailable($this->buildPaymentContext()));
        return $this->htmlResponse();
    }

    public function submitPaymentAction(string $paymentMethod = ''): ResponseInterface
    {
        try {
            $this->paymentMethodRegistry->getAvailableMethod($paymentMethod, $this->buildPaymentContext());
        } catch (PaymentMethodNotAvailableException) {
            $this->backToStep('payment', $this->translate('checkout_payment_method_required'));
        }
        $this->checkoutService->setPaymentMethod($this->request, $paymentMethod);
        return $this->redirect('review');
    }

    public function reviewAction(): ResponseInterface
    {
        $shippingSelection = $this->resolveShippingSelectionForReview();
        $basket = $this->checkoutService->getBasketViewModel($this->request);
        $this->priceQuoteService->freeze($this->request, $basket);
        $address = $this->checkoutService->getAddress($this->request);
        $paymentMethodIdentifier = $this->checkoutService->getPaymentMethod($this->request);

        $enrichEvent = new EnrichCheckoutReviewEvent($this->request);
        $this->eventDispatcher->dispatch($enrichEvent);

        $paymentMethod = $this->findPaymentMethod($paymentMethodIdentifier);
        $this->view->assignMultiple([
            'basket' => $basket,
            'totals' => $this->quoteTotals($basket, $address, $paymentMethod),
            'address' => $address,
            'paymentMethod' => $paymentMethod,
            'shippingSelection' => $shippingSelection,
            'deliveryAddress' => $this->checkoutService->getDeliveryAddress($this->request),
            'giftMessage' => $this->checkoutService->getGiftMessage($this->request),
            'withdrawalWaiverRequired' => $this->withdrawalExclusionService->basketRequiresWaiver($basket),
            'withdrawalExemptPersonalizedItems' => $this->withdrawalExclusionService->basketContainsPersonalized($basket),
        ] + $enrichEvent->getVariables());
        return $this->htmlResponse();
    }

    /**
     * A discount that no longer applies - an expired voucher, say - would refuse the order anyway; the
     * review then shows the goods alone with the reason, and placing the order reports it again.
     */
    private function quoteTotals(BasketViewModel $basket, Address $address, ?PaymentMethodInterface $paymentMethod): ?OrderTotals
    {
        $selections = new CheckoutSelections(
            $this->checkoutService->getShippingOption($this->request),
            $this->checkoutService->getDeliveryAddress($this->request),
            $this->checkoutService->getGiftMessage($this->request)
        );
        try {
            return $this->orderCreationService->quote($this->request, $basket, $selections, $address, $paymentMethod);
        } catch (OrderPlacementExceptionInterface $exception) {
            $this->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
            return null;
        }
    }

    /**
     * The basket or address may have changed since the shipping step, so the stored choice can be gone.
     */
    private function resolveShippingSelectionForReview(): ShippingSelection
    {
        try {
            return $this->shippingQuoteService->resolveSelection(
                $this->configurationFactory->create($this->request),
                $this->shippingContext(),
                $this->checkoutService->getShippingOption($this->request),
                $this->request
            );
        } catch (MissingShippingOptionException|NoShippingOptionAvailableException) {
            $this->backToStep('shippingMethod', $this->translate('checkout_shipping_option_unavailable'));
        }
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

    public function finalizeAction(bool $termsAccepted = false, bool $withdrawalWaiverAccepted = false): ResponseInterface
    {
        $address = $this->checkoutService->getAddress($this->request);
        $paymentMethodIdentifier = $this->checkoutService->getPaymentMethod($this->request);
        $choices = new CheckoutChoices(
            $this->checkoutService->getShippingOption($this->request),
            $this->checkoutService->getDeliveryAddress($this->request),
            $this->checkoutService->getGiftMessage($this->request),
            $termsAccepted,
            $withdrawalWaiverAccepted
        );

        try {
            $result = $this->orderPlacementService->place($this->request, $address, $paymentMethodIdentifier, $choices);
        } catch (OrderPlacementExceptionInterface $exception) {
            $this->backToStep($this->stepToCorrect($exception), $exception->getMessage());
        }

        if ($result->requiresRedirect()) {
            return new RedirectResponse($result->getRedirectUrl());
        }

        return $this->redirect('thankYou', null, null, [
            'order' => $result->getOrder()->getUid(),
            'hash' => $this->orderTokenService->generateToken($result->getOrder()),
        ]);
    }

    /**
     * The checkout step where the customer can correct what made the placement fail.
     */
    private function stepToCorrect(OrderPlacementExceptionInterface $exception): string
    {
        return match (true) {
            $exception instanceof IncompleteAddressException => 'address',
            $exception instanceof PaymentMethodNotAvailableException => 'payment',
            $exception instanceof MissingShippingOptionException, $exception instanceof NoShippingOptionAvailableException => 'shippingMethod',
            default => 'review',
        };
    }

    /**
     * The order id on the URL is caller-controlled and the plugin's cHash is not an authorization secret, so
     * the thank-you page - which mints the invoice, withdrawal and order capability tokens - is gated exactly
     * like {@see OrderController::showAction()}: the caller must own the order or present its order token.
     */
    public function thankYouAction(int $order, ?string $hash = null): ResponseInterface
    {
        $orderObject = $this->checkoutService->getOrder($order);
        if ($orderObject === null) {
            return $this->redirect('list', 'Product');
        }
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $isOwner = $frontendUserUid !== 0 && $orderObject->getFrontendUser() === $frontendUserUid;
        if (!$isOwner && !$this->orderTokenService->isValid($orderObject, $hash)) {
            return $this->redirect('list', 'Product');
        }
        $this->view->assignMultiple([
            'order' => $orderObject,
            'invoiceHash' => $this->invoiceTokenService->generateToken($orderObject),
            'withdrawalHash' => $this->withdrawalTokenService->generateToken($orderObject),
            'orderHash' => $this->orderTokenService->generateToken($orderObject),
            'withdrawalExcluded' => $this->withdrawalExclusionService->isFullyExcluded($orderObject),
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
