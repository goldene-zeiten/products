<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Testing;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads a site set's settings.definitions.yaml for tests that check where those settings end up.
 *
 * Values are returned the way TYPO3 hands site settings to TypoScript constants: an int as its digits, a
 * bool as '1' or '', a string unchanged. List settings are left out - they have no single constant value.
 */
final class SettingsDefinitions
{
    private const SCALAR_TYPES = ['int', 'bool', 'string', 'number'];

    /**
     * @return array<string, string> Constant value per setting identifier
     */
    public static function defaults(string $definitionsFile): array
    {
        $values = [];
        foreach (self::scalarDefinitions($definitionsFile) as $identifier => $definition) {
            $values[$identifier] = self::asConstant($definition['default'] ?? null);
        }
        return $values;
    }

    /**
     * The defaults as a classic static template writes them, where a disabled flag is spelled `0`.
     *
     * @return array<string, string> Constant value per setting identifier
     */
    public static function staticTemplateDefaults(string $definitionsFile): array
    {
        $values = [];
        foreach (self::scalarDefinitions($definitionsFile) as $identifier => $definition) {
            $default = $definition['default'] ?? null;
            $values[$identifier] = $default === false ? '0' : self::asConstant($default);
        }
        return $values;
    }

    /**
     * A value per setting that differs from its default, to prove a site-level override wins.
     *
     * @return array<string, int|bool|string|float>
     */
    public static function overrides(string $definitionsFile): array
    {
        $values = [];
        foreach (self::scalarDefinitions($definitionsFile) as $identifier => $definition) {
            $default = $definition['default'] ?? null;
            $values[$identifier] = match ($definition['type']) {
                'int' => (int)$default + 4711,
                'number' => (float)$default + 2.5,
                'bool' => !$default,
                default => 'override-' . $identifier,
            };
        }
        return $values;
    }

    /**
     * Turns a flat identifier map into the nested tree a site configuration's `settings` key expects.
     *
     * @param array<string, mixed> $flat
     * @return array<string, mixed>
     */
    public static function toTree(array $flat): array
    {
        $tree = [];
        foreach ($flat as $identifier => $value) {
            $node = &$tree;
            foreach (explode('.', $identifier) as $segment) {
                if (!isset($node[$segment]) || !is_array($node[$segment])) {
                    $node[$segment] = [];
                }
                $node = &$node[$segment];
            }
            $node = $value;
            unset($node);
        }
        return $tree;
    }

    /**
     * Reads a setting out of the settings tree a plugin receives, by its identifier minus the `products.`
     * prefix the plugin settings do not carry.
     *
     * @param array<string, mixed> $pluginSettings
     */
    public static function pluginValue(array $pluginSettings, string $identifier): ?string
    {
        $node = $pluginSettings;
        foreach (explode('.', preg_replace('/^products\./', '', $identifier) ?? $identifier) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }
        return is_scalar($node) ? (string)$node : null;
    }

    public static function asConstant(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @return array<string, array{type: string, default?: mixed}>
     */
    private static function scalarDefinitions(string $definitionsFile): array
    {
        $definitions = Yaml::parseFile($definitionsFile)['settings'] ?? [];
        return array_filter(
            $definitions,
            static fn(array $definition): bool => in_array($definition['type'] ?? '', self::SCALAR_TYPES, true)
        );
    }
}
