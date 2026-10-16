<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Reads the `migration.*` extension configuration of products_core, where the integrator declares legacy
 * behaviour the upgrade wizards cannot detect on their own.
 */
final class LegacyMigrationConfiguration
{
    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration) {}

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->all()[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->all()[$key] ?? $default;
        return is_scalar($value) ? (int)$value : $default;
    }

    public function getBool(string $key): bool
    {
        return (bool)($this->all()[$key] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        try {
            $configuration = $this->extensionConfiguration->get('products_core', 'migration');
        } catch (ExtensionConfigurationPathDoesNotExistException) {
            return [];
        }
        return is_array($configuration) ? $configuration : [];
    }
}
