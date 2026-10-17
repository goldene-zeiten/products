<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use GoldeneZeiten\Products\Core\Event\CollectMigratedLegacyColumnsEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Reports every column of a legacy table that carries data although no wizard migrates it, so the
 * integrator learns about content the cleanup would drop. Columns are read from the live schema, which
 * covers columns other extensions added to the legacy table as well; columns another extension migrates are
 * named through {@see CollectMigratedLegacyColumnsEvent}.
 */
final class LegacyColumnReporter
{
    /**
     * @var string[]
     */
    private const SYSTEM_COLUMNS = [
        'uid',
        'pid',
        'tstamp',
        'crdate',
        'cruser_id',
        'sorting',
        'deleted',
        'hidden',
        'editlock',
        'sys_language_uid',
        'l18n_parent',
        'l18n_diffsource',
        'l10n_parent',
        'l10n_diffsource',
        'l10n_state',
        't3_origuid',
        't3ver_oid',
        't3ver_wsid',
        't3ver_state',
        't3ver_stage',
        't3ver_count',
        't3ver_tstamp',
        't3ver_move_id',
        't3ver_label',
    ];

    /**
     * Values meaning "nothing set" although they are no column default, e.g. a unit factor of 1.
     *
     * @var array<string, string[]>
     */
    private const NEUTRAL_VALUES = [
        'unit_factor' => ['1'],
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * @param string[] $handledColumns columns a wizard migrates or deliberately reports itself
     */
    public function report(string $legacyTable, array $handledColumns, OutputInterface $output): void
    {
        foreach ($this->candidateColumns($legacyTable, $handledColumns) as $column) {
            $rows = $this->countRowsWithData($legacyTable, $column);
            if ($rows > 0) {
                $output->writeln(sprintf(
                    '<comment>%s: %d rows have data in "%s", which is not migrated.</comment>',
                    $legacyTable,
                    $rows,
                    $column->getName()
                ));
            }
        }
    }

    /**
     * @param string[] $handledColumns
     * @return Column[]
     */
    private function candidateColumns(string $legacyTable, array $handledColumns): array
    {
        $event = new CollectMigratedLegacyColumnsEvent($legacyTable);
        $this->eventDispatcher->dispatch($event);
        $skippedColumns = array_map(strtolower(...), array_merge(self::SYSTEM_COLUMNS, $handledColumns, $event->getColumns()));
        $columns = $this->connectionPool->getConnectionForTable($legacyTable)
            ->createSchemaManager()
            ->introspectTable($legacyTable)
            ->getColumns();
        return array_filter(
            $columns,
            static fn(Column $column): bool => !in_array(strtolower($column->getName()), $skippedColumns, true)
        );
    }

    private function countRowsWithData(string $legacyTable, Column $column): int
    {
        $type = $column->getType();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($legacyTable);
        $queryBuilder->getRestrictions()->removeAll();
        $hasData = match (true) {
            // An empty string, and the "0" of an unrestricted access group or an unset option, are no data.
            $type instanceof StringType, $type instanceof TextType => $queryBuilder->expr()->notIn(
                $column->getName(),
                $queryBuilder->createNamedParameter(
                    ['', '0', ...self::NEUTRAL_VALUES[strtolower($column->getName())] ?? []],
                    Connection::PARAM_STR_ARRAY
                )
            ),
            $type instanceof IntegerType, $type instanceof SmallIntType, $type instanceof BigIntType,
            $type instanceof DecimalType, $type instanceof FloatType => $queryBuilder->expr()->neq(
                $column->getName(),
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            ),
            default => null,
        };
        if ($hasData === null) {
            return 0;
        }
        return (int)$queryBuilder->count('uid')
            ->from($legacyTable)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $hasData
            )
            ->executeQuery()
            ->fetchOne();
    }
}
