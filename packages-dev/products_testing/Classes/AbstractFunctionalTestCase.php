<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Testing;

use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for every functional test in this monorepo.
 *
 * The dependencies every test here needs - TYPO3's install extension, the shop core, and this test
 * extension carrying the base classes, doubles and shared fixtures - are merged in by {@see setUp()}
 * rather than declared as property defaults. A subclass therefore declares only what *it* adds:
 *
 *     protected array $testExtensionsToLoad = ['goldene-zeiten/products-search'];
 *
 * and never has to repeat the mandatory ones just because overriding a property replaces it.
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected function setUp(): void
    {
        // The testing framework reads all three while booting the instance in parent::setUp(), so what
        // every test here needs has to be in place before it is called. Merging rather than declaring
        // property defaults keeps the properties free for a subclass to override without having to repeat
        // any of this.
        //
        // `install` is needed because this project's extensions declare themselves Composer-only, which
        // skips the classic-mode metadata merge that would otherwise infer the dependency.
        $this->coreExtensionsToLoad = array_merge(
            [
                'install',
            ],
            $this->coreExtensionsToLoad,
        );
        $this->testExtensionsToLoad = array_merge(
            [
                'goldene-zeiten/products-core',
                'goldene-zeiten/products-testing',
            ],
            $this->testExtensionsToLoad,
        );
        // Extbase's reflection cache runs on TransientMemoryBackend to avoid HMAC issues in teardown. A
        // subclass adding its own configuration keeps it; setting this key itself deliberately wins.
        $this->configurationToUseInTestInstance = array_replace_recursive(
            [
                'SYS' => [
                    'caching' => [
                        'cacheConfigurations' => [
                            'extbase' => [
                                'backend' => TransientMemoryBackend::class,
                            ],
                        ],
                    ],
                ],
            ],
            $this->configurationToUseInTestInstance,
        );

        parent::setUp();
    }

    /**
     * Absolute path of a fixture shared across packages, so an add-on never has to copy one.
     */
    protected static function sharedFixture(string $relativePath): string
    {
        return dirname(__DIR__) . '/Fixtures/' . $relativePath;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        GeneralUtility::purgeInstances();
        gc_collect_cycles();
    }
}
