<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentCallbackUrls;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the absolute URLs a redirect payment method hands to its gateway: where to send the customer
 * back to, where to send them if they abandon, and where to post the asynchronous confirmation.
 *
 * All three are fixed paths handled by middleware rather than plugin actions {@see PaymentReturnMiddleware,
 * PaymentWebhookMiddleware}: a gateway appends its own parameters to the URL it calls, which a
 * cHash-validated route would reject. The order is proven by its signed token instead. The customer-facing
 * two still require a configured checkout page - that is where the middleware redirects the browser once it
 * has finalized {@see checkoutStepUrl()}.
 */
final class PaymentUrlFactory
{
    public const WEBHOOK_PATH = '/products/payment/webhook';
    public const RETURN_PATH = '/products/payment/return';
    public const CANCEL_PATH = '/products/payment/cancel';

    /**
     * The query parameter carrying the HMAC that proves a callback belongs to one of this shop's orders.
     * Deliberately not "token": a gateway appends its own parameters to the return URL - PayPal's is called
     * `token` - and a collision would let its value overwrite ours on the shared callback path.
     */
    public const SIGNATURE_PARAM = 'signature';
    public const ORDER_PARAM = 'order';

    private const PLUGIN_NAMESPACE = 'tx_productscore_checkout';

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
        $hasCheckoutPage = (int)$site->getSettings()->get('products.pids.checkoutPage', 0) > 0;

        return new PaymentCallbackUrls(
            $hasCheckoutPage ? $this->signedPathUrl($site, $order, $token, self::RETURN_PATH) : '',
            $hasCheckoutPage ? $this->signedPathUrl($site, $order, $token, self::CANCEL_PATH) : '',
            $this->signedPathUrl($site, $order, $token, self::WEBHOOK_PATH)
        );
    }

    /**
     * The checkout-page action the payment-return middleware redirects the browser to once it has captured
     * the payment - a clean, cHash-valid URL free of whatever the gateway appended. An empty string when no
     * checkout page is configured, which the middleware treats as a redirect to the site root.
     *
     * @param array<string, int|string> $arguments
     */
    public function checkoutStepUrl(Site $site, string $action, array $arguments = []): string
    {
        $checkoutPage = (int)$site->getSettings()->get('products.pids.checkoutPage', 0);
        if ($checkoutPage === 0) {
            return '/';
        }

        return (string)$site->getRouter()->generateUri($checkoutPage, [
            self::PLUGIN_NAMESPACE => [
                'controller' => 'Checkout',
                'action' => $action,
                ...$arguments,
            ],
        ]);
    }

    /**
     * The gateway is not a browser: it follows no session and appends its own parameters, so the callback
     * lives on a fixed path carrying only the signed order, never a cHash-routed plugin action.
     */
    private function signedPathUrl(Site $site, Order $order, string $token, string $path): string
    {
        $query = http_build_query([self::ORDER_PARAM => $order->getUid(), self::SIGNATURE_PARAM => $token]);

        return rtrim((string)$site->getBase(), '/') . $path . '?' . $query;
    }
}
