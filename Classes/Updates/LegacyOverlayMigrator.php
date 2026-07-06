<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Generic `*_language` overlay migration shared by every TtProducts*UpgradeWizard: dedupe via
 * LegacyOverlayDeduplicator, resolve the already-migrated parent via the migration map, then insert
 * an l10n_parent-linked overlay row. A winner whose parent was never migrated (orphan FK) is recorded
 * with a sentinel local uid of 0 so it is treated as handled and not reconsidered on the next run.
 */
final class LegacyOverlayMigrator
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyOverlayDeduplicator $overlayDeduplicator,
    ) {}

    public function hasPending(OverlayMigrationConfig $config): bool
    {
        return $this->pendingWinners($config) !== [];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mapAdditionalFields winner row -> extra `values()` columns
     */
    public function migrate(OutputInterface $output, OverlayMigrationConfig $config, callable $mapAdditionalFields): void
    {
        if (!$this->migrationHelper->tablesExist($config->legacyLanguageTable)) {
            return;
        }
        $this->reportLosers($output, $config, $this->deduplicate($config)['losers']);
        foreach ($this->pendingWinners($config) as $winner) {
            $this->migrateOverlay($output, $config, $winner, $mapAdditionalFields);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingWinners(OverlayMigrationConfig $config): array
    {
        if (!$this->migrationHelper->tablesExist($config->legacyLanguageTable)) {
            return [];
        }
        return array_values(array_filter(
            $this->deduplicate($config)['winners'],
            fn(array $winner): bool => $this->migrationHelper->resolveLocalUid(
                $config->legacyLanguageTable,
                (int)$winner['uid'],
                $config->localTable
            ) === null
        ));
    }

    /**
     * @return array{winners: array<int, array<string, mixed>>, losers: array<int, array<string, mixed>>}
     */
    private function deduplicate(OverlayMigrationConfig $config): array
    {
        return $this->overlayDeduplicator->deduplicate(
            $this->fetchLanguageRows($config->legacyLanguageTable),
            $config->parentField
        );
    }

    /**
     * @param array<int, array<string, mixed>> $losers
     */
    private function reportLosers(OutputInterface $output, OverlayMigrationConfig $config, array $losers): void
    {
        foreach ($losers as $loser) {
            $output->writeln(sprintf(
                '<comment>Skipped duplicate %s uid %d (parent %d, language %d).</comment>',
                $config->legacyLanguageTable,
                (int)$loser['uid'],
                (int)$loser[$config->parentField],
                (int)$loser['sys_language_uid']
            ));
        }
    }

    /**
     * @param array<string, mixed> $winner
     * @param callable(array<string, mixed>): array<string, mixed> $mapAdditionalFields
     */
    private function migrateOverlay(OutputInterface $output, OverlayMigrationConfig $config, array $winner, callable $mapAdditionalFields): void
    {
        $parentLocalUid = $this->migrationHelper->resolveLocalUid(
            $config->legacyParentTable,
            (int)$winner[$config->parentField],
            $config->localTable
        );
        if ($parentLocalUid === null) {
            $output->writeln(sprintf(
                '<comment>Skipped %s uid %d: parent uid %d was never migrated.</comment>',
                $config->legacyLanguageTable,
                (int)$winner['uid'],
                (int)$winner[$config->parentField]
            ));
            $this->migrationHelper->recordMapping($config->legacyLanguageTable, (int)$winner['uid'], $config->localTable, 0);
            return;
        }
        $this->insertOverlay($config, $winner, $parentLocalUid, $mapAdditionalFields);
    }

    /**
     * @param array<string, mixed> $winner
     * @param callable(array<string, mixed>): array<string, mixed> $mapAdditionalFields
     */
    private function insertOverlay(OverlayMigrationConfig $config, array $winner, int $parentLocalUid, callable $mapAdditionalFields): void
    {
        $values = array_merge([
            'pid' => $this->fetchPid($config->localTable, $parentLocalUid),
            'hidden' => (int)$winner['hidden'],
            'sys_language_uid' => (int)$winner['sys_language_uid'],
            'l10n_parent' => $parentLocalUid,
        ], $mapAdditionalFields($winner));
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($config->localTable);
        $queryBuilder->insert($config->localTable)->values($values)->executeStatement();
        $localUid = (int)$this->connectionPool->getConnectionForTable($config->localTable)->lastInsertId();
        $this->migrationHelper->recordMapping($config->legacyLanguageTable, (int)$winner['uid'], $config->localTable, $localUid);
    }

    private function fetchPid(string $localTable, int $localUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($localTable);
        $queryBuilder->getRestrictions()->removeAll();
        $pid = $queryBuilder->select('pid')->from($localTable)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($localUid, ParameterType::INTEGER)))
            ->executeQuery()->fetchOne();
        return (int)$pid;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLanguageRows(string $legacyLanguageTable): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($legacyLanguageTable);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($legacyLanguageTable)->executeQuery()->fetchAllAssociative();
    }
}
