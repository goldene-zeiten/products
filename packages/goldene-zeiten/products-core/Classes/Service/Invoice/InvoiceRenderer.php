<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Invoice;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\OrderSettingsResolver;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders invoice HTML using ViewFactoryInterface (headless, no request needed).
 */
final class InvoiceRenderer
{
    private const DEFAULT_TEMPLATE_ROOT_PATHS = ['EXT:products_core/Resources/Private/Templates/Invoice/'];
    private const DEFAULT_PARTIAL_ROOT_PATHS = ['EXT:products_core/Resources/Private/Partials/'];
    private const DEFAULT_LAYOUT_ROOT_PATHS = ['EXT:products_core/Resources/Private/Layouts/'];

    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
        private readonly OrderSettingsResolver $settingsResolver
    ) {}

    public function render(Order $order): string
    {
        $settings = $this->settingsResolver->getSettings($order);
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: $this->settingsResolver->getPathsSetting($settings, 'products.invoice.templateRootPaths', self::DEFAULT_TEMPLATE_ROOT_PATHS),
            partialRootPaths: $this->settingsResolver->getPathsSetting($settings, 'products.invoice.partialRootPaths', self::DEFAULT_PARTIAL_ROOT_PATHS),
            layoutRootPaths: $this->settingsResolver->getPathsSetting($settings, 'products.invoice.layoutRootPaths', self::DEFAULT_LAYOUT_ROOT_PATHS),
        ));
        $view->assignMultiple([
            'order' => $order,
            'moneyLocale' => $this->settingsResolver->getLocale($order),
            'seller' => [
                'name' => $this->setting($settings, 'products.withdrawal.trader.name'),
                'address' => $this->setting($settings, 'products.withdrawal.trader.address'),
                'vatId' => $this->setting($settings, 'products.invoice.vatId'),
                'taxNumber' => $this->setting($settings, 'products.invoice.taxNumber'),
            ],
        ]);

        return $view->render('Invoice');
    }

    private function setting(SettingsInterface $settings, string $path): string
    {
        $value = $settings->has($path) ? $settings->get($path) : '';
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
