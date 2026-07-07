<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Checkout\CheckoutService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementExceptionInterface;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class CheckoutController extends ActionController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly PaymentContextFactory $paymentContextFactory,
        private readonly OrderPlacementService $orderPlacementService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly CreditPointsService $creditPointsService,
        private readonly ShippingCostService $shippingCostService
    ) {}

    public function addressAction(): ResponseInterface
    {
        $address = $this->checkoutService->getAddress($this->request);
        $this->view->assign('address', $address);
        return $this->htmlResponse();
    }

    public function submitAddressAction(Address $address): ResponseInterface
    {
        $this->checkoutService->setAddress($this->request, $address);
        return $this->redirect($this->shippingCostService->isEnabled() ? 'shippingMethod' : 'payment');
    }

    public function shippingMethodAction(): ResponseInterface
    {
        if (!$this->shippingCostService->isEnabled()) {
            return $this->redirect('payment');
        }
        $address = $this->checkoutService->getAddress($this->request);
        $this->view->assignMultiple([
            'shippingMethods' => $this->shippingCostService->resolveAvailable($this->checkoutService->getBasketViewModel($this->request), $address->getCountry()),
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
        $address = $this->checkoutService->getAddress($this->request);
        $paymentMethodIdentifier = $this->checkoutService->getPaymentMethod($this->request);
        $shippingMethodUid = $this->checkoutService->getShippingMethod($this->request);

        $this->view->assignMultiple([
            'basket' => $basket,
            'address' => $address,
            'paymentMethod' => $this->findPaymentMethod($paymentMethodIdentifier),
            'creditPointsBalance' => $this->creditPointsBalance(),
            'shippingMethod' => $this->shippingCostService->findMethod($shippingMethodUid),
        ]);
        return $this->htmlResponse();
    }

    /**
     * 0 both hides the "spend points" input (feature disabled or guest) and doubles as "nothing
     * to spend" - the template only needs a single number to decide whether to render it.
     */
    private function creditPointsBalance(): int
    {
        if (!$this->creditPointsService->isEnabled()) {
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

    public function finalizeAction(int $spendPoints = 0): ResponseInterface
    {
        $address = $this->checkoutService->getAddress($this->request);
        $paymentMethodIdentifier = $this->checkoutService->getPaymentMethod($this->request);
        $shippingMethodUid = $this->checkoutService->getShippingMethod($this->request);

        try {
            $result = $this->orderPlacementService->place($this->request, $address, $paymentMethodIdentifier, $spendPoints, $shippingMethodUid);
        } catch (OrderPlacementExceptionInterface $exception) {
            $this->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('review');
        }

        if ($result->requiresRedirect()) {
            return new RedirectResponse($result->getRedirectUrl());
        }

        return $this->redirect('thankYou', null, null, ['order' => $result->getOrder()->getUid()]);
    }

    public function paymentReturnAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }

    public function paymentCancelAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }

    public function thankYouAction(int $order): ResponseInterface
    {
        $orderObject = $this->checkoutService->getOrder($order);
        if ($orderObject === null) {
            return $this->redirect('list', 'Product');
        }
        $this->view->assign('order', $orderObject);
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
