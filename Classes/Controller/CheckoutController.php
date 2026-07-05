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
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementExceptionInterface;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
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
        private readonly FrontendUserResolver $frontendUserResolver
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

        $this->view->assign('basket', $basket);
        $this->view->assign('address', $address);
        $this->view->assign('paymentMethod', $this->findPaymentMethod($paymentMethodIdentifier));
        return $this->htmlResponse();
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

    public function finalizeAction(): ResponseInterface
    {
        $address = $this->checkoutService->getAddress($this->request);
        $paymentMethodIdentifier = $this->checkoutService->getPaymentMethod($this->request);

        try {
            $result = $this->orderPlacementService->place($this->request, $address, $paymentMethodIdentifier);
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
