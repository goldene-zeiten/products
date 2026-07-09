<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional;

use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for all functional tests of this extension.
 *
 * `core`, `backend`, `frontend`, `extbase` and `fluid` are already part of
 * `FunctionalTestCase::$defaultCoreExtensionsToLoad` and must not be repeated here. `install` is
 * an additional hard `composer.json` require of this extension (its upgrade wizards use
 * `TYPO3\CMS\Install` classes) but is no longer a hard TYPO3 core dependency as of v14, so it must
 * be loaded explicitly for the extension to boot at all in a test.
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
    ];

    /**
     * Extbase's `ReflectionService` (a `SingletonInterface`) persists its class-schema cache to
     * the `extbase` cache frontend from its own `__destruct()`, signed with an HMAC
     * (`VariableFrontend`'s `AuthenticatedMessageDeserializer` guards against cache-poisoning via
     * PHP object injection). That destructor is not guaranteed to run before the *last* test in a
     * run finishes - PHP may defer it to the process's own final object teardown, which happens
     * after PHPUnit's `backupGlobals` has already restored `$GLOBALS` to its pre-suite (i.e.
     * `TYPO3_CONF_VARS`-less) snapshot, so `HashService::hmac()` reads a missing encryption key
     * and raises a raw, unhandled PHP warning unattributable to any single test.
     *
     * `VariableFrontend::set()`/`get()` skip the HMAC path entirely for a backend implementing
     * `TransientBackendInterface` (`if (!$this->backend instanceof TransientBackendInterface)`),
     * since a transient cache has nothing left to poison between requests anyway. Using one for
     * `extbase` in tests removes the encryption-key dependency at its source, regardless of when
     * the destructor actually fires - a functional test rebuilds its whole environment per test
     * either way, so a persistent reflection cache buys nothing here that a transient one doesn't.
     *
     * A subclass that declares its own `$configurationToUseInTestInstance` would replace this
     * default outright (PHP property defaults don't merge across a class hierarchy) - merge this
     * `SYS.caching.cacheConfigurations.extbase` key into any such override instead of dropping it.
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

    /**
     * Between two tests of the same test case, `FunctionalTestCase::setUp()` already purges
     * leftover singleton instances itself before rebuilding the container for the next test -
     * while `$GLOBALS['TYPO3_CONF_VARS']` from the test that just ran is still valid. There is no
     * "next test" to trigger that purge after the very last one, so this closes that one gap too,
     * as a general safety net for any other cache/singleton with similar destructor-time behaviour
     * beyond the `extbase` case addressed above.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        GeneralUtility::purgeInstances();
        gc_collect_cycles();
    }
}
