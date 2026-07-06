<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional;

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
}
