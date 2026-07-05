<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,subtitle,slug,description,item_number,ean',
        'iconfile' => 'EXT:products/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'title, subtitle, slug, item_number, ean, price, tax_class, categories, in_stock, basket_min_quantity, basket_max_quantity, weight, is_offer, is_highlight, description, articles, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource'],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_products_domain_model_product',
                'foreign_table_where' => 'AND {#tx_products_domain_model_product}.{#pid}=###CURRENT_PID### AND {#tx_products_domain_model_product}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'subtitle' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.subtitle',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'slug' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.slug',
            'config' => [
                'type' => 'slug',
                'generatorOptions' => [
                    'fields' => ['title'],
                    'fieldSeparator' => '/',
                    'prefixParentPageSlug' => true,
                    'replacements' => [
                        '/' => '',
                    ],
                ],
                'fallbackCharacter' => '-',
                'eval' => 'uniqueInSite',
            ],
        ],
        'item_number' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.item_number',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'ean' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.ean',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'price' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.price',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'tax_class' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tax_class',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_taxclass',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'categories' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.categories',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectTree',
                'foreign_table' => 'tx_products_domain_model_category',
                'MM' => 'tx_products_product_category_mm',
                'treeConfig' => [
                    'parentField' => 'parent_category',
                    'appearance' => [
                        'expandAll' => true,
                        'showHeader' => true,
                    ],
                ],
            ],
        ],
        'in_stock' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.in_stock',
            'config' => [
                'type' => 'number',
                'size' => 10,
            ],
        ],
        'basket_min_quantity' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.basket_min_quantity',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'basket_max_quantity' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.basket_max_quantity',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'weight' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.weight',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'is_offer' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.is_offer',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'is_highlight' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.is_highlight',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'articles' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.articles',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_products_domain_model_article',
                'foreign_field' => 'product',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top',
                    'showSynchronizationLink' => 1,
                    'showPossibleLocalizationRecords' => 1,
                    'showAllLocalizationLink' => 1,
                ],
            ],
        ],
    ],
];
