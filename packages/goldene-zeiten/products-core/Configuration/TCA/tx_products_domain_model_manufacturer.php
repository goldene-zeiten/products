<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Backend\Form\CountryItemsProcFunc;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer',
        'label' => 'name',
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
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Manufacturer.svg',
    ],
    'types' => [
        '1' => ['showitem' => '--div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.tab_general, name, brand_name, logo, description, page, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.tab_contact, website, --palette--;;electronicContact, --palette--;;postalAddress, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.tab_eu_responsible, eu_responsible_name, eu_responsible_address, eu_responsible_email, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden'],
    ],
    'palettes' => [
        'electronicContact' => [
            'showitem' => 'email, contact_url',
        ],
        'postalAddress' => [
            'showitem' => 'street, --linebreak--, zip, city, --linebreak--, country',
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
                'foreign_table' => 'tx_products_domain_model_manufacturer',
                'foreign_table_where' => 'AND {#tx_products_domain_model_manufacturer}.{#pid}=###CURRENT_PID### AND {#tx_products_domain_model_manufacturer}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'name' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'brand_name' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.brand_name',
            'description' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.brand_name.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'logo' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.logo',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'maxitems' => 1,
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'page' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.page',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'maxitems' => 1,
                'size' => 1,
            ],
        ],
        'website' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.website',
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
            ],
        ],
        'email' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.email',
            'config' => [
                'type' => 'email',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'contact_url' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.contact_url',
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
            ],
        ],
        'street' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.street',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'zip' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.zip',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'city' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.city',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'country' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.country',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => CountryItemsProcFunc::class . '->getCountries',
            ],
        ],
        'eu_responsible_name' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.eu_responsible_name',
            'description' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.eu_responsible_name.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'eu_responsible_address' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.eu_responsible_address',
            'config' => [
                'type' => 'text',
                'rows' => 4,
            ],
        ],
        'eu_responsible_email' => [
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_manufacturer.eu_responsible_email',
            'config' => [
                'type' => 'email',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
    ],
];
