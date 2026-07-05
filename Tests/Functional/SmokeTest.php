<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SmokeTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/products',
    ];

    /**
     * @test
     */
    public function extensionCanBeLoaded(): void
    {
        self::assertTrue(true);
    }
}
