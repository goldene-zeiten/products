<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Dataset Import (acceptance tests only)',
    'description' => 'CLI command wrapping typo3/testing-framework\'s DataSet::import() to seed the disposable acceptance-test instance. Never installed outside Tests/Acceptance/Instance.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
    ],
];
