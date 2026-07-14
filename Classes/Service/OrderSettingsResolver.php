<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Shared by every order-triggered renderer (OrderMailService, InvoiceRenderer) that needs the
 * order's originating site's settings outside of a normal frontend request context.
 */
final class OrderSettingsResolver
{
    public function __construct(
        private readonly SiteFinder $siteFinder
    ) {}

    public function getSettings(Order $order): SettingsInterface
    {
        try {
            return $this->siteFinder->getSiteByIdentifier($order->getSiteIdentifier())->getSettings();
        } catch (SiteNotFoundException) {
            return $this->getDefaultSettings();
        }
    }

    /**
     * For notifications with no order/site context of their own (e.g. a low-stock warning) -
     * the first configured site's settings, same fallback `getSettings()` uses internally.
     */
    public function getDefaultSettings(): SettingsInterface
    {
        $sites = $this->siteFinder->getAllSites();
        $site = reset($sites);

        return $site !== false ? $site->getSettings() : SiteSettings::createFromSettingsTree([]);
    }

    /**
     * @param non-empty-array<string> $default
     * @return non-empty-array<string>
     */
    public function getPathsSetting(SettingsInterface $settings, string $path, array $default): array
    {
        return $settings->has($path) ? $settings->get($path) : $default;
    }
}
