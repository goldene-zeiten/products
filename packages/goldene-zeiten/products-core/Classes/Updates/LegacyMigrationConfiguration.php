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
     * `migration.columnCategories`, e.g. "form:Manufacturer, quality:Tags".
     *
     * @return array<string, string> legacy column => title of its root category
     */
    public function getColumnCategories(): array
    {
        $columns = [];
        foreach ($this->getList('columnCategories') as $entry) {
            [$column, $title] = array_map(trim(...), explode(':', $entry, 2)) + [1 => ''];
            if ($column !== '') {
                $columns[$column] = $title !== '' ? $title : $column;
            }
        }
        return $columns;
    }

    /**
     * A map of legacy TypoScript keys, e.g. `migration.orderPaymentMethods` = "10:cashondelivery, 90:paypal".
     *
     * @return array<int, string> legacy key => target, the target may contain further colons
     */
    public function getKeyMap(string $key): array
    {
        $map = [];
        foreach ($this->getList($key) as $entry) {
            [$legacyKey, $target] = array_map(trim(...), explode(':', $entry, 2)) + [1 => ''];
            if (ctype_digit($legacyKey) && $target !== '') {
                $map[(int)$legacyKey] = $target;
            }
        }
        return $map;
    }

    /**
     * @return string[]
     */
    public function getList(string $key): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $this->getString($key)))));
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
