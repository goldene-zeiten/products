<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Express\ExpressBasketFactory;
use GoldeneZeiten\Products\Core\Express\ExpressBasketTokenService;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Express\GooglePay\Express\Exception\ExpressPaymentDeclinedException;
use GoldeneZeiten\Products\Express\GooglePay\Express\GooglePayExpressCheckoutProvider;
use GoldeneZeiten\Products\Express\GooglePay\Express\GooglePayExpressCheckoutService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * The frontend seam of the Google Pay express checkout. {@see buttonAction} renders the button and hands
 * its JS the signed basket token; {@see confirmAction} authorizes the token and creates the paid order once
 * the buyer has authorized in the Google Pay sheet, answering with the thank-you URL.
 *
 * The confirm action runs as its own typeNum PAGE so the Google Pay JS receives raw JSON. Live shipping has
 * no action here - the sheet's `onPaymentDataChanged` callback is answered client-side from the core
 * shipping-quote endpoint.
 */
final class ExpressCheckoutController extends ActionController
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ExpressCheckoutProviderRegistry $providerRegistry,
        private readonly ExpressBasketFactory $expressBasketFactory,
        private readonly ExpressBasketTokenService $basketTokenService,
        private readonly GooglePayExpressCheckoutService $checkoutService
    ) {}

    public function buttonAction(): ResponseInterface
    {
        $basket = $this->basketService->getBasketViewModel($this->request);
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $context = new ExpressCheckoutContext($basket->getTotalGross(), $basket->getCurrency(), $frontendUserUid);
        $provider = $this->providerRegistry->get(GooglePayExpressCheckoutProvider::IDENTIFIER);
        if ($basket->isEmpty() || !$provider->isAvailable($context)) {
            return $this->htmlResponse();
        }

        $this->view->assignMultiple([
            'configuration' => $provider->getButtonConfiguration($context),
            'basketToken' => $this->basketTokenService->issue($this->expressBasketFactory->createFromBasket($basket, $frontendUserUid)),
        ]);

        return $this->htmlResponse();
    }

    public function confirmAction(): ResponseInterface
    {
        $body = $this->jsonBody();
        try {
            $order = $this->checkoutService->confirm(
                $this->request,
                $this->basketService->getBasketViewModel($this->request),
                $this->buildAddress(is_array($body['address'] ?? null) ? $body['address'] : []),
                (string)($body['shippingOption'] ?? ''),
                (string)($body['token'] ?? '')
            );
        } catch (ExpressPaymentDeclinedException) {
            return new JsonResponse(['error' => 'payment_declined'], 402);
        }

        return new JsonResponse(['redirectUrl' => $this->thankYouUrl($order)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $body = json_decode((string)$this->request->getBody(), true);

        return is_array($body) ? $body : [];
    }

    /**
     * @param array<string, mixed> $address
     */
    private function buildAddress(array $address): Address
    {
        return new Address(
            email: (string)($address['email'] ?? ''),
            firstName: (string)($address['firstName'] ?? ''),
            lastName: (string)($address['lastName'] ?? ''),
            street: (string)($address['street'] ?? ''),
            zip: (string)($address['postalCode'] ?? ''),
            city: (string)($address['city'] ?? ''),
            country: (string)($address['country'] ?? ''),
            state: (string)($address['state'] ?? '')
        );
    }

    private function thankYouUrl(Order $order): string
    {
        $site = $this->request->getAttribute('site');
        $checkoutPageUid = $site instanceof Site ? (int)$site->getSettings()->get('products.pids.checkoutPage') : 0;

        return $this->uriBuilder->reset()
            ->setCreateAbsoluteUri(true)
            ->setTargetPageUid($checkoutPageUid)
            ->uriFor('thankYou', ['order' => $order->getUid()], 'Checkout', 'ProductsCore', 'Checkout');
    }
}
