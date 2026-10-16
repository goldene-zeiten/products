<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Updates;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Reads the `migration.*` extension configuration of products_personalization: which tt_products
 * selection-list columns become options, which of their choices personalize the goods, and which lists
 * get a free text next to them.
 */
final class LegacyOptionColumns
{
    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration) {}

    /**
     * `migration.optionColumns`, e.g. "color:Ring size, size:Engraving font".
     *
     * @return array<string, string> legacy column => option title
     */
    public function getSelectionColumns(): array
    {
        return $this->getTitledList('optionColumns');
    }

    /**
     * `migration.optionTextColumns`, e.g. "size:Engraving text": a free text option next to the list.
     *
     * @return array<string, string> legacy column => title of the free text option
     */
    public function getTextColumns(): array
    {
        return $this->getTitledList('optionTextColumns');
    }

    /**
     * Whether the choices of a column personalize the goods, except the choices meaning "none".
     */
    public function isPersonalizingChoice(string $column, string $value): bool
    {
        if (!in_array($column, $this->getList('personalizingOptionColumns'), true)) {
            return false;
        }
        $noneValues = array_map(mb_strtolower(...), $this->getList('optionNoneValues'));
        return !in_array(mb_strtolower($value), $noneValues, true);
    }

    /**
     * @return array<string, string>
     */
    private function getTitledList(string $key): array
    {
        $columns = [];
        foreach ($this->getList($key) as $entry) {
            [$column, $title] = array_map(trim(...), explode(':', $entry, 2)) + [1 => ''];
            if ($column !== '') {
                $columns[$column] = $title !== '' ? $title : $column;
            }
        }
        return $columns;
    }

    /**
     * @return string[]
     */
    private function getList(string $key): array
    {
        try {
            $value = $this->extensionConfiguration->get('products_personalization', 'migration/' . $key);
        } catch (ExtensionConfigurationPathDoesNotExistException) {
            return [];
        }
        $value = is_scalar($value) ? (string)$value : '';
        return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn(string $entry): bool => $entry !== ''));
    }
}
