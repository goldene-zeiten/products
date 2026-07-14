<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category',
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
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Category.svg',
    ],
    'types' => [
        '1' => ['showitem' => '--div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.tab_general, --palette--;;titleAndSlug, parent_category, image, description, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.tab_discount, --palette--;;discount, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.tab_notification, --palette--;;notification, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource, --div--;LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.tab_access, --palette--;;access'],
    ],
    'palettes' => [
        'titleAndSlug' => [
            'showitem' => 'title, --linebreak--, slug, hide_in_slug_path',
        ],
        'discount' => [
            'showitem' => 'discount_percent, discount_disabled',
        ],
        'notification' => [
            'showitem' => 'notification_email, notification_recipient_name',
        ],
        'access' => [
            'showitem' => 'perms_userid, perms_groupid, --linebreak--, perms_user, perms_group, perms_everybody',
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
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_products_domain_model_category',
                'foreign_table_where' => 'AND {#tx_products_domain_model_category}.{#pid}=###CURRENT_PID### AND {#tx_products_domain_model_category}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'slug' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.slug',
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
        'image' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.image',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'maxitems' => 1,
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'notification_email' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.notification_email',
            'config' => [
                'type' => 'email',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'notification_recipient_name' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.notification_recipient_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'discount_percent' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.discount_percent',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'discount_disabled' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.discount_disabled',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'hide_in_slug_path' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.hide_in_slug_path',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'parent_category' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.parent_category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectTree',
                'foreign_table' => 'tx_products_domain_model_category',
                'treeConfig' => [
                    'parentField' => 'parent_category',
                    'appearance' => [
                        'expandAll' => true,
                        'showHeader' => true,
                    ],
                ],
                'default' => 0,
            ],
        ],
        'perms_userid' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.perms_userid',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'be_users',
                'default' => 0,
            ],
        ],
        'perms_groupid' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.perms_groupid',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'be_groups',
                'default' => 0,
            ],
        ],
        'perms_user' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.perms_user',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.show'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.edit'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.delete'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.new'],
                ],
            ],
        ],
        'perms_group' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.perms_group',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.show'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.edit'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.delete'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.new'],
                ],
            ],
        ],
        'perms_everybody' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_category.perms_everybody',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.show'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.edit'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.delete'],
                    ['label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:permission.new'],
                ],
            ],
        ],
    ],
];
