<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product',
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
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Product.svg',
    ],
    'types' => [
        '1' => ['showitem' => '--div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tab_general, title, subtitle, slug, --palette--;;identifiers, categories, description, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tab_prices, --palette--;;pricing, --palette--;;discount, price_tiers, price_periods, --linebreak--, --palette--;;unitPricing, tax_class, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tab_stock, --palette--;;stock, --palette--;;basketLimits, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tab_shipping, --palette--;;shipping, shipping_point, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tab_marketing, credit_points, --palette--;;flags, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tab_media, images, downloads, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tab_relations, articles, related_products, accessory_products, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource'],
    ],
    'palettes' => [
        'identifiers' => [
            'showitem' => 'item_number, ean',
        ],
        'pricing' => [
            'showitem' => 'price, --linebreak--, direct_cost, deposit',
        ],
        'discount' => [
            'showitem' => 'discount_percent, discount_disabled',
        ],
        'stock' => [
            'showitem' => 'in_stock, unlimited_stock',
        ],
        'basketLimits' => [
            'showitem' => 'basket_min_quantity, basket_max_quantity',
        ],
        'shipping' => [
            'showitem' => 'weight, bulky, shipping_class',
        ],
        'flags' => [
            'showitem' => 'is_offer, is_highlight',
        ],
        'unitPricing' => [
            'showitem' => 'content_amount, content_unit',
        ],
    ],
    'columns' => [
        'crdate' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.creationDate',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
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
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'subtitle' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.subtitle',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'slug' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.slug',
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
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.item_number',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'ean' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.ean',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'price' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.price',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'direct_cost' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.direct_cost',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'deposit' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.deposit',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'discount_percent' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.discount_percent',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'discount_disabled' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.discount_disabled',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'price_tiers' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.price_tiers',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_products_domain_model_pricetier',
                'foreign_field' => 'product',
                'foreign_sortby' => 'sorting',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top',
                ],
            ],
        ],
        'price_periods' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.price_periods',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_products_domain_model_priceperiod',
                'foreign_field' => 'product',
                'foreign_sortby' => 'sorting',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top',
                ],
            ],
        ],
        'tax_class' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.tax_class',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_taxclass',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'shipping_point' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.shipping_point',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_shippingpoint',
                'maxitems' => 1,
            ],
        ],
        'categories' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.categories',
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
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.in_stock',
            'config' => [
                'type' => 'number',
                'size' => 10,
            ],
        ],
        'unlimited_stock' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.unlimited_stock',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'basket_min_quantity' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.basket_min_quantity',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'basket_max_quantity' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.basket_max_quantity',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'weight' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.weight',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'bulky' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.bulky',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'shipping_class' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.shipping_class',
            'description' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.shipping_class.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => '',
                        'value' => '',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.shipping_class.hazmat',
                        'value' => 'hazmat',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.shipping_class.freight',
                        'value' => 'freight',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.shipping_class.refrigerated',
                        'value' => 'refrigerated',
                    ],
                ],
                'default' => '',
            ],
        ],
        'content_amount' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.content_amount',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'content_unit' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.content_unit',
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
        'credit_points' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.credit_points',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'is_offer' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.is_offer',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'is_highlight' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.is_highlight',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'images' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.images',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'maxitems' => 50,
            ],
        ],
        'downloads' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.downloads',
            'config' => [
                'type' => 'file',
                'allowed' => 'pdf,doc,docx,xls,xlsx,zip,txt',
                'maxitems' => 20,
            ],
        ],
        'articles' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.articles',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_products_domain_model_article',
                'foreign_field' => 'product',
                'foreign_sortby' => 'sorting',
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
        'related_products' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.related_products',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_products_domain_model_product',
                'MM' => 'tx_products_product_related_mm',
                'size' => 8,
            ],
        ],
        'accessory_products' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_product.accessory_products',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_products_domain_model_product',
                'MM' => 'tx_products_product_accessory_mm',
                'size' => 8,
            ],
        ],
    ],
];
