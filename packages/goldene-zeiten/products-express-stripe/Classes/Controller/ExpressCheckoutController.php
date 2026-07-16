<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Express\ExpressBasketFactory;
use GoldeneZeiten\Products\Core\Express\ExpressBasketTokenService;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Express\Stripe\Express\Exception\ExpressPaymentDeclinedException;
use GoldeneZeiten\Products\Express\Stripe\Express\StripeExpressCheckoutProvider;
use GoldeneZeiten\Products\Express\Stripe\Express\StripeExpressConfirmService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * The frontend seam of the Stripe express checkout. Its two actions are the mirror image of the wallet
 * flow: {@see buttonAction} renders the Express Checkout Element on the basket page and hands its JS the
 * static configuration plus the per-basket signed token, and {@see confirmAction} is the endpoint that JS
 * posts to once the buyer has authorized the wallet - it settles the PaymentIntent, creates the paid order
 * and answers with the thank-you URL to send the browser to.
 *
 * The confirm action recomputes the charge from the live session basket rather than trusting the client,
 * so it runs as a normal in-page request (a dedicated typeNum PAGE) with the session and full configuration
 * available - unlike the session-less, token-proven shipping-rate callback.
 */
final class ExpressCheckoutController extends ActionController
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ExpressCheckoutProviderRegistry $providerRegistry,
        private readonly ExpressBasketFactory $expressBasketFactory,
        private readonly ExpressBasketTokenService $basketTokenService,
        private readonly StripeExpressConfirmService $confirmService
    ) {}

    public function buttonAction(): ResponseInterface
    {
        $basket = $this->basketService->getBasketViewModel($this->request);
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $context = new ExpressCheckoutContext($basket->getTotalGross(), $basket->getCurrency(), $frontendUserUid);
        $provider = $this->providerRegistry->get(StripeExpressCheckoutProvider::IDENTIFIER);
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
        $body = $this->request->getParsedBody();
        $body = is_array($body) ? $body : [];

        try {
            $order = $this->confirmService->confirm(
                $this->request,
                $this->basketService->getBasketViewModel($this->request),
                $this->buildAddress($body),
                (string)($body['shippingOption'] ?? ''),
                (string)($body['paymentMethodId'] ?? '')
            );
        } catch (ExpressPaymentDeclinedException) {
            return new JsonResponse(['error' => 'payment_declined'], 402);
        }

        return new JsonResponse(['redirectUrl' => $this->thankYouUrl($order)]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function buildAddress(array $body): Address
    {
        return new Address(
            email: (string)($body['email'] ?? ''),
            firstName: (string)($body['firstName'] ?? ''),
            lastName: (string)($body['lastName'] ?? ''),
            company: (string)($body['company'] ?? ''),
            street: (string)($body['street'] ?? ''),
            zip: (string)($body['postalCode'] ?? ''),
            city: (string)($body['city'] ?? ''),
            country: (string)($body['country'] ?? ''),
            state: (string)($body['state'] ?? '')
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
