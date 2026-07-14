<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentCallbackUrls;
use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the absolute URLs a redirect payment method hands to its gateway: where to send the customer
 * back to, where to send them if they abandon, and where to post the asynchronous confirmation.
 *
 * The customer-facing two are actions of the checkout plugin, so they need a configured checkout page.
 * The webhook is called by the gateway rather than a browser and therefore bypasses the page tree
 * entirely {@see PaymentWebhookMiddleware}.
 */
final class PaymentUrlFactory
{
    public const WEBHOOK_PATH = '/products/payment/webhook';

    private const PLUGIN_NAMESPACE = 'tx_products_checkout';

    public function __construct(
        private readonly PaymentTokenService $paymentTokenService
    ) {}

    public function createFor(Order $order, ServerRequestInterface $request): PaymentCallbackUrls
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return new PaymentCallbackUrls();
        }
        $token = $this->paymentTokenService->generateToken($order);

        return new PaymentCallbackUrls(
            $this->actionUrl($site, $order, $token, 'paymentReturn'),
            $this->actionUrl($site, $order, $token, 'paymentCancel'),
            $this->webhookUrl($site, $order, $token)
        );
    }

    /**
     * Without a configured checkout page there is nowhere to send the customer back to; an empty URL is
     * the honest answer, and lets the payment method decide whether it can work without one.
     */
    private function actionUrl(Site $site, Order $order, string $token, string $action): string
    {
        $checkoutPage = (int)$site->getSettings()->get('products.pids.checkoutPage', 0);
        if ($checkoutPage === 0) {
            return '';
        }

        return (string)$site->getRouter()->generateUri($checkoutPage, [
            self::PLUGIN_NAMESPACE => [
                'controller' => 'Checkout',
                'action' => $action,
                'order' => $order->getUid(),
                'token' => $token,
            ],
        ]);
    }

    private function webhookUrl(Site $site, Order $order, string $token): string
    {
        $query = http_build_query(['order' => $order->getUid(), 'token' => $token]);

        return rtrim((string)$site->getBase(), '/') . self::WEBHOOK_PATH . '?' . $query;
    }
}
