<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;

final class SmokeTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function extensionCanBeLoaded(): void
    {
        self::assertGreaterThan(0, $this->get(Typo3Version::class)->getMajorVersion());
    }

    /**
     * Only applies to TYPO3 v13; excluded via `--exclude-group not-core-14`
     * when the test suite runs against a TYPO3 v14 core.
     */
    #[Test]
    #[Group('not-core-14')]
    public function testSuiteRunsOnTypo3V13(): void
    {
        $majorVersion = $this->get(Typo3Version::class)->getMajorVersion();
        if ($majorVersion !== 13) {
            self::markTestSkipped('This test only applies to TYPO3 v13.');
        }

        self::assertSame(13, $majorVersion);
    }

    /**
     * Only applies to TYPO3 v14; excluded via `--exclude-group not-core-13`
     * when the test suite runs against a TYPO3 v13 core.
     */
    #[Test]
    #[Group('not-core-13')]
    public function testSuiteRunsOnTypo3V14(): void
    {
        $majorVersion = $this->get(Typo3Version::class)->getMajorVersion();
        if ($majorVersion !== 14) {
            self::markTestSkipped('This test only applies to TYPO3 v14.');
        }

        self::assertSame(14, $majorVersion);
    }
}
