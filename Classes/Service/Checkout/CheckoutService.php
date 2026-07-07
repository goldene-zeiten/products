<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Checkout;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class CheckoutService
{
    private const SESSION_KEY_ADDRESS = 'tx_products_checkout_address';
    private const SESSION_KEY_PAYMENT = 'tx_products_checkout_payment';
    private const SESSION_KEY_SHIPPING_METHOD = 'tx_products_checkout_shipping_method';
    private const SESSION_KEY_DELIVERY_ADDRESS = 'tx_products_checkout_delivery_address';
    private const SESSION_KEY_GIFT_MESSAGE = 'tx_products_checkout_gift_message';

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

    public function setShippingMethod(ServerRequestInterface $request, int $shippingMethodUid): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_SHIPPING_METHOD, $shippingMethodUid);
            $frontendUser->storeSessionData();
        }
    }

    public function getShippingMethod(ServerRequestInterface $request): int
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            return (int)$frontendUser->getKey('ses', self::SESSION_KEY_SHIPPING_METHOD);
        }
        return 0;
    }

    /**
     * Null clears any previously chosen alternate delivery address, e.g. when the shopper
     * unchecks "ship to a different address" again.
     */
    public function setDeliveryAddress(ServerRequestInterface $request, ?Address $deliveryAddress): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_DELIVERY_ADDRESS, $deliveryAddress !== null ? serialize($deliveryAddress) : null);
            $frontendUser->storeSessionData();
        }
    }

    /**
     * Null means "ship to the billing address" - meaningfully different from an Address with
     * blank fields, so no alternate address is ever returned as a real-but-empty Address.
     */
    public function getDeliveryAddress(ServerRequestInterface $request): ?Address
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $data = $frontendUser->getKey('ses', self::SESSION_KEY_DELIVERY_ADDRESS);
            if (!empty($data)) {
                $address = unserialize((string)$data, ['allowed_classes' => [Address::class]]);
                if ($address instanceof Address) {
                    return $address;
                }
            }
        }
        return null;
    }

    public function setGiftMessage(ServerRequestInterface $request, string $giftMessage): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_GIFT_MESSAGE, $giftMessage);
            $frontendUser->storeSessionData();
        }
    }

    public function getGiftMessage(ServerRequestInterface $request): string
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            return (string)$frontendUser->getKey('ses', self::SESSION_KEY_GIFT_MESSAGE);
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

    public function clearCheckoutSession(ServerRequestInterface $request): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_ADDRESS, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_PAYMENT, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_SHIPPING_METHOD, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_DELIVERY_ADDRESS, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_GIFT_MESSAGE, null);
            $frontendUser->storeSessionData();
        }
    }
}
