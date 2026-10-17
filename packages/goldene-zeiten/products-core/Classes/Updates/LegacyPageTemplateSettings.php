<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\TypoScript\AST\AstBuilderInterface;
use TYPO3\CMS\Core\TypoScript\TypoScriptStringFactory;

/**
 * The tt_products list settings a page configured in its TypoScript templates: the template records of the
 * page and every page above it, merged from the root down like TYPO3 merges them. Constants and conditions
 * are not evaluated; legacy shops set these values plainly.
 */
final class LegacyPageTemplateSettings
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly TypoScriptStringFactory $typoScriptStringFactory,
        private readonly AstBuilderInterface $astBuilder
    ) {}

    /**
     * @return int[] the storage folders `plugin.tt_products.pid_list` lists
     */
    public function getPidList(int $pageUid): array
    {
        $pidList = $this->pluginSettings($pageUid)['pid_list'] ?? '';
        return array_values(array_filter(array_map(intval(...), explode(',', is_scalar($pidList) ? (string)$pidList : ''))));
    }

    /**
     * The products a list page shows, `conf.tt_products.ALL.limit`, falling back to `limit`; 0 when unset.
     */
    public function getLimit(int $pageUid): int
    {
        return (int)$this->listSetting($pageUid, 'limit');
    }

    /**
     * The order of a list, `conf.tt_products.ALL.orderBy`, falling back to `orderBy`; empty when unset.
     */
    public function getOrderBy(int $pageUid): string
    {
        return trim($this->listSetting($pageUid, 'orderBy'));
    }

    private function listSetting(int $pageUid, string $key): string
    {
        $settings = $this->pluginSettings($pageUid);
        $all = $settings['conf.']['tt_products.']['ALL.'] ?? [];
        $value = (is_array($all) ? $all[$key] ?? null : null) ?? $settings[$key] ?? '';
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginSettings(int $pageUid): array
    {
        $typoScript = $this->typoScriptStringFactory->parseFromString($this->templateConfig($pageUid), $this->astBuilder)->toArray();
        $plugin = $typoScript['plugin.']['tt_products.'] ?? [];
        return is_array($plugin) ? $plugin : [];
    }

    /**
     * The setup of the visible templates from the root page down to the page, in that order.
     */
    private function templateConfig(int $pageUid): string
    {
        $config = '';
        foreach (array_reverse($this->rootline($pageUid)) as $rootlinePageUid) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_template');
            $queryBuilder->getRestrictions()->removeAll();
            $result = $queryBuilder->select('config')
                ->from('sys_template')
                ->where(
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($rootlinePageUid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
                )
                ->orderBy('sorting')
                ->executeQuery();
            while ($row = $result->fetchAssociative()) {
                $config .= (string)$row['config'] . "\n";
            }
        }
        return $config;
    }

    /**
     * @return int[] the page and its ancestors, the page first
     */
    private function rootline(int $pageUid): array
    {
        $rootline = [];
        while ($pageUid > 0 && !in_array($pageUid, $rootline, true)) {
            $rootline[] = $pageUid;
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
            $queryBuilder->getRestrictions()->removeAll();
            $pageUid = (int)$queryBuilder->select('pid')
                ->from('pages')
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchOne();
        }
        return $rootline;
    }
}
