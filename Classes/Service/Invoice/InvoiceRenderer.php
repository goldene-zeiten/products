<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Invoice;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\OrderSettingsResolver;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders invoice HTML using ViewFactoryInterface (headless, no request needed).
 */
final class InvoiceRenderer
{
    private const DEFAULT_TEMPLATE_ROOT_PATHS = ['EXT:products/Resources/Private/Templates/Invoice/'];
    private const DEFAULT_PARTIAL_ROOT_PATHS = ['EXT:products/Resources/Private/Partials/'];
    private const DEFAULT_LAYOUT_ROOT_PATHS = ['EXT:products/Resources/Private/Layouts/'];

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
        $view->assign('order', $order);

        return $view->render('Invoice');
    }
}
