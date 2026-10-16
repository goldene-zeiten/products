<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_optionitem',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'sortby' => 'sorting',
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
        '1' => ['showitem' => 'title, value, surcharge, personalizing, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource'],
    ],
    'columns' => [
        'parent_option' => [
            'label' => 'Option',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_optionitem.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'value' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_optionitem.value',
            'description' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_optionitem.value.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 64,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'personalizing' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_optionitem.personalizing',
            'description' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_optionitem.personalizing.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'surcharge' => [
            'label' => 'LLL:EXT:products_personalization/Resources/Private/Language/locallang_tca.xlf:tx_productspersonalization_domain_model_optionitem.surcharge',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => 0,
            ],
        ],
    ],
];
