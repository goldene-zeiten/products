<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Service\Checkout\CheckoutService;
use GoldeneZeiten\Products\Service\MailService;
use GoldeneZeiten\Products\Service\Payment\PaymentService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class CheckoutController extends ActionController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PaymentService $paymentService,
        private readonly MailService $mailService
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
        $this->view->assign('paymentMethods', $this->paymentService->getAvailablePaymentMethods());
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
        $paymentMethod = $this->paymentService->getPaymentMethod($paymentMethodIdentifier);

        $this->view->assign('basket', $basket);
        $this->view->assign('address', $address);
        $this->view->assign('paymentMethod', $paymentMethod);
        return $this->htmlResponse();
    }

    public function finalizeAction(): ResponseInterface
    {
        $order = $this->checkoutService->finalizeOrder($this->request);

        // Send confirmation mail
        try {
            $this->mailService->sendOrderConfirmation($order);
        } catch (\Exception $e) {
            // Log error in real app
        }

        $paymentMethodIdentifier = $order->getPaymentMethod();
        $paymentMethod = $this->paymentService->getPaymentMethod($paymentMethodIdentifier);

        if ($paymentMethod !== null) {
            return $paymentMethod->process($order, $this->request);
        }

        return $this->redirect('thankYou', null, null, ['order' => $order->getUid()]);
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
}
