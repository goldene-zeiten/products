<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Article.svg',
    ],
    'types' => [
        '1' => ['showitem' => '--div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.tab_general, product, title, slug, --palette--;;identifiers, attribute_values, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.tab_prices, --palette--;;pricing, price_tiers, price_periods, --linebreak--, --palette--;;unitPricing, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.tab_stock, --palette--;;stock, --palette--;;basketLimits, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.tab_shipping, --palette--;;shipping, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.tab_media, images, downloads, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource'],
    ],
    'palettes' => [
        'identifiers' => [
            'showitem' => 'item_number, ean',
        ],
        'pricing' => [
            'showitem' => 'price, price_mode, --linebreak--, direct_cost, deposit',
        ],
        'stock' => [
            'showitem' => 'in_stock, unlimited_stock',
        ],
        'basketLimits' => [
            'showitem' => 'basket_min_quantity, basket_max_quantity',
        ],
        'shipping' => [
            'showitem' => 'weight, bulky',
        ],
        'unitPricing' => [
            'showitem' => 'content_amount, content_unit',
        ],
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
                    [
                        'label' => '',
                        'value' => 0,
                    ],
                ],
                'foreign_table' => 'tx_products_domain_model_article',
                'foreign_table_where' => 'AND {#tx_products_domain_model_article}.{#pid}=###CURRENT_PID### AND {#tx_products_domain_model_article}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'product' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.product',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_product',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'slug' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.slug',
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
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.item_number',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'ean' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.ean',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'price' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.price',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'price_mode' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.price_mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.price_mode.override',
                        'value' => 'override',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.price_mode.surcharge',
                        'value' => 'surcharge',
                    ],
                ],
                'default' => 'override',
            ],
        ],
        'direct_cost' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.direct_cost',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'deposit' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.deposit',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'price_tiers' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.price_tiers',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_products_domain_model_pricetier',
                'foreign_field' => 'article',
                'foreign_sortby' => 'sorting',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top',
                ],
            ],
        ],
        'price_periods' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.price_periods',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_products_domain_model_priceperiod',
                'foreign_field' => 'article',
                'foreign_sortby' => 'sorting',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top',
                ],
            ],
        ],
        'attribute_values' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.attribute_values',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_products_domain_model_attributevalue',
                'foreign_table_where' => 'ORDER BY tx_products_domain_model_attributevalue.sorting',
                'MM' => 'tx_products_article_attributevalue_mm',
                'size' => 8,
            ],
        ],
        'in_stock' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.in_stock',
            'config' => [
                'type' => 'number',
                'size' => 10,
            ],
        ],
        'unlimited_stock' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.unlimited_stock',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'basket_min_quantity' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.basket_min_quantity',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'basket_max_quantity' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.basket_max_quantity',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'weight' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.weight',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'bulky' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.bulky',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'content_amount' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.content_amount',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'content_unit' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.content_unit',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => '-- disabled --',
                        'value' => '',
                    ],
                    [
                        'label' => 'Gram (g)',
                        'value' => 'g',
                    ],
                    [
                        'label' => 'Kilogram (kg)',
                        'value' => 'kg',
                    ],
                    [
                        'label' => 'Ounce (oz)',
                        'value' => 'oz',
                    ],
                    [
                        'label' => 'Pound (lb)',
                        'value' => 'lb',
                    ],
                    [
                        'label' => 'Millilitre (ml)',
                        'value' => 'ml',
                    ],
                    [
                        'label' => 'Litre (l)',
                        'value' => 'l',
                    ],
                    [
                        'label' => 'Fluid ounce (US, fl oz)',
                        'value' => 'fl_oz',
                    ],
                    [
                        'label' => 'Gallon (US, gal)',
                        'value' => 'gal',
                    ],
                    [
                        'label' => 'Millimetre (mm)',
                        'value' => 'mm',
                    ],
                    [
                        'label' => 'Centimetre (cm)',
                        'value' => 'cm',
                    ],
                    [
                        'label' => 'Metre (m)',
                        'value' => 'm',
                    ],
                    [
                        'label' => 'Inch (in)',
                        'value' => 'in',
                    ],
                    [
                        'label' => 'Foot (ft)',
                        'value' => 'ft',
                    ],
                    [
                        'label' => 'Square metre (m²)',
                        'value' => 'm2',
                    ],
                    [
                        'label' => 'Square foot (ft²)',
                        'value' => 'ft2',
                    ],
                ],
                'default' => '',
            ],
        ],
        'images' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.images',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'maxitems' => 50,
            ],
        ],
        'downloads' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_article.downloads',
            'config' => [
                'type' => 'file',
                'allowed' => 'pdf,doc,docx,xls,xlsx,zip,txt',
                'maxitems' => 20,
            ],
        ],
    ],
];
