<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Voucher\Controller\VoucherController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    // The voucher form posts back to the basket page, and only a plugin actually placed on that page is
    // dispatched - so the voucher controller rides the core basket plugin rather than a plugin of its own.
    // Registering onto ProductsCore's plugin keeps the content element unchanged: no new CType appears.
    ExtensionUtility::registerControllerActions(
        'ProductsCore',
        'Basket',
        [
            VoucherController::class => ['apply', 'remove'],
        ],
        [
            VoucherController::class => ['apply', 'remove'],
        ],
    );
})();
