<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionManagementUtility::addTCAcolumns('tx_products_domain_model_article', [
        'personalization_options' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.personalization_options',
            'description' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.personalization_options.description',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_productspersonalization_domain_model_option',
                'foreign_field' => 'article',
                'foreign_sortby' => 'sorting',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top',
                    'useSortable' => true,
                ],
            ],
        ],
    ]);

    ExtensionManagementUtility::addToAllTCAtypes(
        'tx_products_domain_model_article',
        '--div--;LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.tab_personalization, personalization_options',
        '',
        'before:--div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.tab_seo',
    );
})();
