<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Integrity;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * A classic installation autoloads the vendored invoice libraries only through the `autoload` section of
 * ext_emconf.php, so it has to name every PSR-4 namespace dompdf needs - its own and those of every library it
 * requires, transitively.
 */
final class ClassicModeAutoloadTest extends UnitTestCase
{
    private const VENDORED_ROOT_PACKAGE = 'dompdf/dompdf';

    #[Test]
    public function everyNamespaceOfTheVendoredLibrariesIsMapped(): void
    {
        $required = [];
        foreach (self::vendoredPackages(self::VENDORED_ROOT_PACKAGE) as $package) {
            $autoload = self::composerManifest($package)['autoload']['psr-4'] ?? [];
            foreach (array_keys(is_array($autoload) ? $autoload : []) as $namespace) {
                $required[$namespace] = $package;
            }
        }
        $mapped = array_keys(self::emconf()['autoload']['psr-4'] ?? []);

        $missing = array_diff_key($required, array_flip($mapped));

        $this->assertNotSame([], $required, 'The vendored libraries could not be read from the vendor directory.');
        $this->assertSame([], $missing, 'Namespaces missing in the ext_emconf.php autoload section.');
    }

    /**
     * @return string[] the package and every library it requires, transitively; platform requirements excluded
     */
    private static function vendoredPackages(string $rootPackage): array
    {
        $seen = [];
        $queue = [$rootPackage];
        while (($package = array_shift($queue)) !== null) {
            if (isset($seen[$package]) || !is_file(self::vendorDirectory() . $package . '/composer.json')) {
                continue;
            }
            $seen[$package] = true;
            $require = self::composerManifest($package)['require'] ?? [];
            foreach (array_keys(is_array($require) ? $require : []) as $dependency) {
                if (is_string($dependency) && str_contains($dependency, '/')) {
                    $queue[] = $dependency;
                }
            }
        }
        return array_map(strval(...), array_keys($seen));
    }

    /**
     * @return array<string, mixed>
     */
    private static function composerManifest(string $package): array
    {
        $manifest = json_decode((string)file_get_contents(self::vendorDirectory() . $package . '/composer.json'), true);
        return is_array($manifest) ? $manifest : [];
    }

    private static function vendorDirectory(): string
    {
        return dirname(__DIR__, 6) . '/.Build/vendor/';
    }

    /**
     * @return array<string, mixed>
     */
    private static function emconf(): array
    {
        $_EXTKEY = 'products_core';
        $EM_CONF = self::configurationByExtensionKey();
        require dirname(__DIR__, 3) . '/ext_emconf.php';
        $configuration = $EM_CONF[$_EXTKEY] ?? null;
        return is_array($configuration) ? $configuration : [];
    }

    /**
     * The shape ext_emconf.php fills in.
     *
     * @return array<string, mixed>
     */
    private static function configurationByExtensionKey(): array
    {
        return [];
    }
}
