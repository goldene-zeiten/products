<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Solr Index (acceptance tests only)',
    'description' => 'CLI command that initializes EXT:solr\'s index queue for the acceptance site and indexes it into the live Solr server. Never installed outside the Solr acceptance combination.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'solr' => '13.1.3-14.99.99',
        ],
    ],
];
