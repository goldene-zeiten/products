<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Offline\Settings;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\OrderSettingsResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;

/**
 * Reads the site settings `products.payment.<method>.*` of the offline payment methods, for the checkout
 * of the current request or for a placed order.
 */
final class OfflinePaymentSettings
{
    public function __construct(
        private readonly OrderSettingsResolver $orderSettingsResolver
    ) {}

    public function forCurrentRequest(): SettingsInterface
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $site = $request instanceof ServerRequestInterface ? $request->getAttribute('site') : null;
        return $site instanceof SiteInterface ? $site->getSettings() : SiteSettings::createFromSettingsTree([]);
    }

    public function forOrder(Order $order): SettingsInterface
    {
        return $this->orderSettingsResolver->getSettings($order);
    }

    public function isEnabled(SettingsInterface $settings, string $method): bool
    {
        $key = 'products.payment.' . $method . '.enabled';
        return $settings->has($key) && (bool)$settings->get($key);
    }

    public function getString(SettingsInterface $settings, string $method, string $key, string $default = ''): string
    {
        $path = 'products.payment.' . $method . '.' . $key;
        $value = $settings->has($path) ? $settings->get($path) : $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * @return string[] shipping providers or options, e.g. "pickup" or "ups:express"
     */
    public function getShippingList(SettingsInterface $settings, string $method, string $key, string $default = ''): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $this->getString($settings, $method, $key, $default)))));
    }

    /**
     * The discount `<method>.discountPercent` grants on the amount, as a negative fee in cents.
     */
    public function discount(SettingsInterface $settings, string $method, PaymentContext $context): int
    {
        $percent = (float)str_replace(',', '.', $this->getString($settings, $method, 'discountPercent', '0'));
        if ($percent <= 0.0) {
            return 0;
        }
        return -(int)round($context->getAmount()->getCents() * $percent / 100);
    }
}
