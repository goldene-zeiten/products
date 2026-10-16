<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option',
        'label' => 'title',
        'label_alt' => 'identifier',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'type' => 'type',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'hideTable' => true,
        'versioningWS' => true,
    ],
    'types' => [
        'select' => ['showitem' => 'type, --palette--;;naming, --palette--;;selectRules, items, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource'],
        'text' => ['showitem' => 'type, --palette--;;naming, --palette--;;rules, --palette--;;text, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource'],
    ],
    'palettes' => [
        'naming' => [
            'showitem' => 'title, identifier',
        ],
        'rules' => [
            'showitem' => 'required, personalizing, --linebreak--, surcharge',
        ],
        'selectRules' => [
            'showitem' => 'required, --linebreak--, surcharge',
        ],
        'text' => [
            'showitem' => 'max_length, character_set',
        ],
    ],
    'columns' => [
        'product' => [
            'label' => 'Product',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'article' => [
            'label' => 'Article',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'type' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.type',
            'l10n_mode' => 'exclude',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.type.select', 'value' => 'select'],
                    ['label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.type.text', 'value' => 'text'],
                ],
                'default' => 'select',
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'identifier' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.identifier',
            'description' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.identifier.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 64,
                'eval' => 'trim,alphanum_x,lower',
                'required' => true,
            ],
        ],
        'required' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.required',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'personalizing' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.personalizing',
            'description' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.personalizing.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 1,
            ],
        ],
        'surcharge' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.surcharge',
            'description' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.surcharge.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'max_length' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.max_length',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'number',
                'size' => 5,
                'range' => [
                    'lower' => 0,
                ],
                'default' => 20,
            ],
        ],
        'character_set' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.character_set',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.character_set.any', 'value' => ''],
                    ['label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.character_set.engraving', 'value' => 'engraving'],
                ],
                'default' => '',
            ],
        ],
        'items' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_option.items',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_productspersonalization_domain_model_optionitem',
                'foreign_field' => 'parent_option',
                'foreign_sortby' => 'sorting',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'top',
                    'useSortable' => true,
                ],
            ],
        ],
    ],
];
