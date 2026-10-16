<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    ExtensionManagementUtility::addStaticFile(
        'products_core',
        'Configuration/TypoScript',
        'ProductsCore'
    );
})();
