<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional;

use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
    ];

    /**
     * Extbase reflection cache uses TransientMemoryBackend to avoid HMAC issues in test teardown.
     * Subclasses must merge this into their own $configurationToUseInTestInstance rather than replacing it.
     */
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'extbase' => [
                        'backend' => TransientMemoryBackend::class,
                    ],
                ],
            ],
        ],
    ];

    protected function tearDown(): void
    {
        parent::tearDown();
        GeneralUtility::purgeInstances();
        gc_collect_cycles();
    }
}
