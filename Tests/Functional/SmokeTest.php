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
        $this->assertGreaterThan(0, $this->get(Typo3Version::class)->getMajorVersion());
    }

    #[Test]
    #[Group('not-core-14')]
    public function testSuiteRunsOnTypo3V13(): void
    {
        $majorVersion = $this->get(Typo3Version::class)->getMajorVersion();
        if ($majorVersion !== 13) {
            $this->markTestSkipped('This test only applies to TYPO3 v13.');
        }

        $this->assertSame(13, $majorVersion);
    }

    #[Test]
    #[Group('not-core-13')]
    public function testSuiteRunsOnTypo3V14(): void
    {
        $majorVersion = $this->get(Typo3Version::class)->getMajorVersion();
        if ($majorVersion !== 14) {
            $this->markTestSkipped('This test only applies to TYPO3 v14.');
        }

        $this->assertSame(14, $majorVersion);
    }
}
