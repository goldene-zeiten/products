<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Resolves a set of configuration values by layering an extension's system-wide configuration under a
 * site's settings: a non-empty site setting overrides the extension-configuration default, an empty one
 * inherits it. This lets a single installation carry a global default while a multi-shop instance runs a
 * different sender or credentials per site.
 *
 * It is the one place an integration reads either source, keeping the resulting configuration value
 * object free of both the settings source and the request. The value object and its field list stay with
 * the integration; only the layering rule lives here, shared across every carrier and gateway.
 */
final class ApiSettingsResolver
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @param string[] $fields the configuration keys to resolve, without the settings prefix
     * @return array<string, string> the resolved value per field, trimmed
     */
    public function resolve(string $extensionKey, string $settingsPrefix, array $fields, ?Site $site): array
    {
        $defaults = $this->extensionDefaults($extensionKey);
        $overrides = $this->siteOverrides($site, $settingsPrefix, $fields);

        $resolved = [];
        foreach ($fields as $field) {
            $resolved[$field] = ($overrides[$field] ?? '') !== ''
                ? $overrides[$field]
                : ($defaults[$field] ?? '');
        }

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function extensionDefaults(string $extensionKey): array
    {
        try {
            $config = $this->extensionConfiguration->get($extensionKey);
        } catch (\Throwable) {
            return [];
        }

        return is_array($config)
            ? array_map(static fn(mixed $value): string => trim((string)$value), $config)
            : [];
    }

    /**
     * @param string[] $fields
     * @return array<string, string>
     */
    private function siteOverrides(?Site $site, string $settingsPrefix, array $fields): array
    {
        if ($site === null) {
            return [];
        }

        $settings = $site->getSettings();
        $overrides = [];
        foreach ($fields as $field) {
            $overrides[$field] = trim((string)$settings->get($settingsPrefix . $field, ''));
        }

        return $overrides;
    }
}
