<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Integrity;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The backend shows a placeholder for every extension without an extension icon, so every package in the monorepo
 * has to ship Resources/Public/Icons/Extension.svg. This keeps a new package from being added without one.
 */
final class ExtensionIconTest extends UnitTestCase
{
    private const ICON_PATH = 'Resources/Public/Icons/Extension.svg';

    #[Test]
    public function everyPackageShipsAnExtensionIcon(): void
    {
        $packageDirectories = array_map(
            dirname(...),
            glob(dirname(__DIR__, 4) . '/*/composer.json') ?: []
        );

        $this->assertGreaterThan(1, count($packageDirectories), 'The package glob found too few packages.');

        $missing = [];
        foreach ($packageDirectories as $packageDirectory) {
            if (!is_file($packageDirectory . '/' . self::ICON_PATH)) {
                $missing[] = basename($packageDirectory);
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Packages without ' . self::ICON_PATH . ': ' . implode(', ', $missing)
        );
    }
}
