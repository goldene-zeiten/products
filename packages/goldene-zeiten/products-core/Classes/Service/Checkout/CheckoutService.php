<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Checkout;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class CheckoutService
{
    private const SESSION_KEY_ADDRESS = 'tx_products_checkout_address';
    private const SESSION_KEY_PAYMENT = 'tx_products_checkout_payment';
    private const SESSION_KEY_SHIPPING_OPTION = 'tx_products_checkout_shipping_option';
    private const SESSION_KEY_DELIVERY_ADDRESS = 'tx_products_checkout_delivery_address';
    private const SESSION_KEY_GIFT_MESSAGE = 'tx_products_checkout_gift_message';

    public function __construct(
        private readonly BasketService $basketService,
        private readonly OrderRepository $orderRepository,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly PriceQuoteService $priceQuoteService
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
     * Prefill checkout from returning customer's last order.
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
     * Fallback to fe_user profile for first-time buyers; address field maps to street.
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

    /**
     * The customer's choice is stored as "provider:option" rather than a record uid, because a carrier
     * that computes its rates through an API has no record to point at.
     */
    public function setShippingOption(ServerRequestInterface $request, string $optionKey): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_SHIPPING_OPTION, $optionKey);
            $frontendUser->storeSessionData();
        }
    }

    public function getShippingOption(ServerRequestInterface $request): string
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            return (string)$frontendUser->getKey('ses', self::SESSION_KEY_SHIPPING_OPTION);
        }
        return '';
    }

    public function setDeliveryAddress(ServerRequestInterface $request, ?Address $deliveryAddress): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_DELIVERY_ADDRESS, $deliveryAddress !== null ? serialize($deliveryAddress) : null);
            $frontendUser->storeSessionData();
        }
    }

    /**
     * Null means "ship to billing address" (distinct from an empty Address).
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
            $frontendUser->setKey('ses', self::SESSION_KEY_SHIPPING_OPTION, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_DELIVERY_ADDRESS, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_GIFT_MESSAGE, null);
            $frontendUser->storeSessionData();
        }
        $this->priceQuoteService->clear($request);
    }
}
