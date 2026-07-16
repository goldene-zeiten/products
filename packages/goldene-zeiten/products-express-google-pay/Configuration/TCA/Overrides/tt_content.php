<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::registerPlugin(
        'ProductsExpressGooglePay',
        'ExpressCheckout',
        'LLL:EXT:products_express_google_pay/Resources/Private/Language/locallang_be.xlf:plugin.express_checkout',
        'EXT:products_express_google_pay/Resources/Public/Icons/Extension.svg'
    );
})();
