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

    public function clearCheckoutSession(ServerRequestInterface $request): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->setKey('ses', self::SESSION_KEY_ADDRESS, null);
            $frontendUser->setKey('ses', self::SESSION_KEY_PAYMENT, null);
            $frontendUser->storeSessionData();
        }
    }
}
