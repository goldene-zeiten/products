<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Checkout;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class CheckoutService
{
    private const SESSION_KEY_ADDRESS = 'tx_products_checkout_address';
    private const SESSION_KEY_PAYMENT = 'tx_products_checkout_payment';

    public function __construct(
        private readonly BasketService $basketService,
        private readonly OrderRepository $orderRepository
    ) {}

    public function setAddress(ServerRequestInterface $request, Address $address): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_ADDRESS, serialize($address));
            $frontendUser->storeSessionData();
        }
    }

    public function getAddress(ServerRequestInterface $request): Address
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $data = $frontendUser->getKey('ses', self::SESSION_KEY_ADDRESS);
            if (!empty($data)) {
                $address = unserialize((string)$data, ['allowed_classes' => [Address::class]]);
                if ($address instanceof Address) {
                    return $address;
                }
            }
        }
        return new Address();
    }

    public function setPaymentMethod(ServerRequestInterface $request, string $paymentMethod): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_PAYMENT, $paymentMethod);
            $frontendUser->storeSessionData();
        }
    }

    public function getPaymentMethod(ServerRequestInterface $request): string
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            return (string)$frontendUser->getKey('ses', self::SESSION_KEY_PAYMENT);
        }
        return '';
    }

    public function getBasketViewModel(ServerRequestInterface $request): BasketViewModel
    {
        return $this->basketService->getBasketViewModel($request);
    }

    public function getOrder(int $orderUid): ?Order
    {
        return $this->orderRepository->findByUid($orderUid);
    }

    public function finalizeOrder(ServerRequestInterface $request): Order
    {
        $basketViewModel = $this->basketService->getBasketViewModel($request);
        $addressDto = $this->getAddress($request);
        $paymentMethod = $this->getPaymentMethod($request);

        if ($basketViewModel->isEmpty()) {
            throw new \RuntimeException('Basket is empty', 1720200000);
        }

        $order = new Order();
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication && ($frontendUser->user['uid'] ?? 0) > 0) {
            $order->setFrontendUser((int)$frontendUser->user['uid']);
        }
        $order->setOrderDate(new \DateTime());
        $order->setOrderNumber('ORD-' . time()); // Simple order number for now
        $order->setEmail($addressDto->getEmail());
        $order->setPaymentMethod($paymentMethod);
        $order->setPaymentStatus(PaymentStatus::OPEN);
        $order->setStatus(OrderStatus::NEW);
        $order->setCurrency($basketViewModel->getCurrency());
        $order->setTotalNet($basketViewModel->getTotalNet());
        $order->setTotalTax($basketViewModel->getTotalTax());
        $order->setTotalGross($basketViewModel->getTotalGross());
        $order->setTaxCountry($addressDto->getCountry());

        $billingAddress = new OrderAddress();
        $billingAddress->setAddressType('billing');
        $billingAddress->setSalutation($addressDto->getSalutation());
        $billingAddress->setFirstName($addressDto->getFirstName());
        $billingAddress->setLastName($addressDto->getLastName());
        $billingAddress->setCompany($addressDto->getCompany());
        $billingAddress->setStreet($addressDto->getStreet());
        $billingAddress->setZip($addressDto->getZip());
        $billingAddress->setCity($addressDto->getCity());
        $billingAddress->setCountry($addressDto->getCountry());
        $order->setBillingAddress($billingAddress);

        $orderItems = new ObjectStorage();
        foreach ($basketViewModel->getItems() as $viewItem) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($viewItem->getProduct()->getUid() ?? 0);
            if ($viewItem->getArticle() !== null) {
                $orderItem->setArticle($viewItem->getArticle()->getUid() ?? 0);
                $orderItem->setArticleTitle($viewItem->getArticle()->getTitle());
            }
            $orderItem->setTitle($viewItem->getProduct()->getTitle());
            $orderItem->setItemNumber($viewItem->getArticle() ? $viewItem->getArticle()->getItemNumber() : $viewItem->getProduct()->getItemNumber());
            $orderItem->setQuantity($viewItem->getQuantity());
            $orderItem->setUnitPriceNet($viewItem->getUnitPriceNet());
            $orderItem->setUnitPriceGross($viewItem->getUnitPriceGross());
            $orderItem->setTaxRate($viewItem->getTaxRate());
            $orderItem->setLineTotalNet($viewItem->getLineTotalNet());
            $orderItem->setLineTotalTax($viewItem->getLineTotalTax());
            $orderItem->setLineTotalGross($viewItem->getLineTotalGross());
            $orderItem->setParentOrder($order);
            $orderItems->attach($orderItem);
        }
        $order->setItems($orderItems);

        $this->orderRepository->add($order);

        $this->basketService->clear($request);
        $this->clearCheckoutSession($request);

        return $order;
    }

    private function clearCheckoutSession(ServerRequestInterface $request): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_ADDRESS, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_PAYMENT, null);
            $frontendUser->storeSessionData();
        }
    }
}
