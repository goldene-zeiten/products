<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Integrity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Every `LLL:EXT:<own extension key>/...xlf:<key>` reference in a package must resolve to an
 * existing trans-unit. Runs against every sibling package that is present in the monorepo.
 */
final class LanguageLabelReferencesTest extends UnitTestCase
{
    private const SCANNED_DIRECTORIES = ['Configuration', 'Classes', 'Resources/Private'];

    private const SCANNED_EXTENSIONS = ['php', 'yaml', 'yml', 'html', 'typoscript', 'tsconfig', 'txt', 'json'];

    #[Test]
    #[DataProvider('packageProvider')]
    public function everyReferencedLabelExists(string $packagePath, string $extensionKey): void
    {
        $references = self::collectReferences($packagePath, $extensionKey);
        $missing = [];
        foreach ($references as $reference => $files) {
            [$relativePath, $key] = explode(':', substr($reference, strlen('LLL:EXT:' . $extensionKey . '/')), 2);
            if (!self::hasTransUnit($packagePath . '/' . $relativePath, $key)) {
                $missing[] = sprintf('%s (used in %s)', $reference, implode(', ', $files));
            }
        }

        $this->assertSame([], $missing, "Missing labels:\n" . implode("\n", $missing));
    }

    #[Test]
    public function theScanFindsReferencesInTheCorePackage(): void
    {
        $references = self::collectReferences(dirname(__DIR__, 3), 'products_core');

        $this->assertNotSame([], $references, 'No label references found, the scan is broken.');
    }

    public static function packageProvider(): \Generator
    {
        $packagesRoot = dirname(__DIR__, 4);
        foreach (glob($packagesRoot . '/*/composer.json') ?: [] as $composerFile) {
            $composer = json_decode((string)file_get_contents($composerFile), true);
            $extensionKey = $composer['extra']['typo3/cms']['extension-key'] ?? null;
            if (!is_string($extensionKey)) {
                continue;
            }
            yield $extensionKey => [
                'packagePath' => dirname($composerFile),
                'extensionKey' => $extensionKey,
            ];
        }
    }

    /**
     * @return array<string, list<string>> reference => files using it
     */
    private static function collectReferences(string $packagePath, string $extensionKey): array
    {
        $pattern = '#LLL:EXT:' . preg_quote($extensionKey, '#') . '/[A-Za-z0-9_/.-]+\.xlf:[A-Za-z0-9_.-]*[A-Za-z0-9_-]#';
        $references = [];
        foreach (self::SCANNED_DIRECTORIES as $directory) {
            if (!is_dir($packagePath . '/' . $directory)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                $packagePath . '/' . $directory,
                \FilesystemIterator::SKIP_DOTS
            ));
            foreach ($files as $file) {
                if (!in_array($file->getExtension(), self::SCANNED_EXTENSIONS, true)) {
                    continue;
                }
                $contents = (string)file_get_contents($file->getPathname());
                preg_match_all($pattern, $contents, $matches);
                $relativeFile = substr($file->getPathname(), strlen($packagePath) + 1);
                foreach ($matches[0] as $reference) {
                    $references[$reference][] = $relativeFile;
                }
                foreach (self::collectPrefixedReferences($contents, $extensionKey) as $reference) {
                    $references[$reference][] = $relativeFile;
                }
            }
        }

        return $references;
    }

    /**
     * Resolves `translate('key')` calls in a class that builds its label as
     * `'LLL:EXT:<key>/<file>.xlf:' . $key`.
     *
     * @return list<string>
     */
    private static function collectPrefixedReferences(string $contents, string $extensionKey): array
    {
        $prefixPattern = '#[\'"](LLL:EXT:' . preg_quote($extensionKey, '#') . '/[A-Za-z0-9_/.-]+\.xlf:)[\'"]\s*\.#';
        if (preg_match($prefixPattern, $contents, $prefix) !== 1) {
            return [];
        }
        preg_match_all('#->translate\(\'([A-Za-z0-9_.-]*[A-Za-z0-9_-])\'\)#', $contents, $calls);

        return array_map(static fn(string $key): string => $prefix[1] . $key, $calls[1]);
    }

    private static function hasTransUnit(string $xlfPath, string $key): bool
    {
        if (!is_file($xlfPath)) {
            return false;
        }
        $document = new \DOMDocument();
        if (!$document->load($xlfPath)) {
            return false;
        }
        foreach ($document->getElementsByTagName('trans-unit') as $unit) {
            if ($unit->getAttribute('id') === $key) {
                return true;
            }
        }

        return false;
    }
}
