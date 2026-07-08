<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Invoice;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\OrderSettingsResolver;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders the invoice HTML source that InvoicePdfService turns into a PDF. Uses the generic
 * ViewFactoryInterface (StandaloneView's replacement, see Deprecation-104773) since this runs
 * outside a frontend request context - no request needed for a headless render.
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
