<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Checkout;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
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
        private readonly OrderRepository $orderRepository,
        private readonly FrontendUserResolver $frontendUserResolver
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
        return $this->addressFromLastOrder($request) ?? $this->addressFromProfile($request) ?? new Address();
    }

    /**
     * Prefills the checkout with a returning customer's most recent billing address, so they
     * don't have to retype it on every order - only relevant before anything has been entered
     * into the current checkout session yet.
     */
    private function addressFromLastOrder(ServerRequestInterface $request): ?Address
    {
        $frontendUserUid = $this->frontendUserResolver->getUid($request);
        if ($frontendUserUid === 0) {
            return null;
        }
        $lastOrder = $this->orderRepository->findByFrontendUser($frontendUserUid)->getFirst();
        if (!$lastOrder instanceof Order) {
            return null;
        }
        $billingAddress = $lastOrder->getBillingAddress();
        if (!$billingAddress instanceof OrderAddress) {
            return null;
        }
        return new Address(
            email: $lastOrder->getEmail(),
            salutation: $billingAddress->getSalutation(),
            firstName: $billingAddress->getFirstName(),
            lastName: $billingAddress->getLastName(),
            company: $billingAddress->getCompany(),
            street: $billingAddress->getStreet(),
            zip: $billingAddress->getZip(),
            city: $billingAddress->getCity(),
            country: $billingAddress->getCountry()
        );
    }

    /**
     * Falls back to the logged-in fe_user's own profile fields when there is no prior order to
     * prefill from (a first-time logged-in buyer) - lower priority than the last order's address,
     * since a returning customer's most recent shipping choice is more likely correct than a
     * possibly-stale profile field. fe_users has no structured street/house-number split, so its
     * free-text `address` field maps onto Address's `street`.
     */
    private function addressFromProfile(ServerRequestInterface $request): ?Address
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication || ($frontendUser->user['uid'] ?? 0) <= 0) {
            return null;
        }
        $user = $frontendUser->user;
        return new Address(
            email: (string)($user['email'] ?? ''),
            firstName: (string)($user['first_name'] ?? ''),
            lastName: (string)($user['last_name'] ?? ''),
            company: (string)($user['company'] ?? ''),
            street: (string)($user['address'] ?? ''),
            zip: (string)($user['zip'] ?? ''),
            city: (string)($user['city'] ?? ''),
            country: (string)($user['country'] ?? '')
        );
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
