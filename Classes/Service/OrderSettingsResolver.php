<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use GoldeneZeiten\Products\Domain\Model\Order;
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
            return $this->fallbackSettings();
        }
    }

    /**
     * @param non-empty-array<string> $default
     * @return non-empty-array<string>
     */
    public function getPathsSetting(SettingsInterface $settings, string $path, array $default): array
    {
        return $settings->has($path) ? $settings->get($path) : $default;
    }

    private function fallbackSettings(): SettingsInterface
    {
        $sites = $this->siteFinder->getAllSites();
        $site = reset($sites);

        return $site !== false ? $site->getSettings() : SiteSettings::createFromSettingsTree([]);
    }
}
