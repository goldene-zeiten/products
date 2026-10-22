<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The manufacturer records built from a legacy manufacturer column ({@see LegacyMigrationConfiguration::getManufacturerColumn()}):
 * one per distinct value, recorded in the migration map and found by its name regardless of case and
 * accents. Their address data is completed from the legacy address record and website, never overwritten.
 */
final class LegacyManufacturers
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly CountryProvider $countryProvider,
    ) {}

    public function find(string $column, string $name): ?int
    {
        $key = LegacyComparisonKey::of($name);
        foreach ($this->names($column) as $uid => $existing) {
            if (LegacyComparisonKey::of($existing) === $key) {
                return $uid;
            }
        }
        return null;
    }

    public function findOrCreate(string $column, string $name, int $pid): int
    {
        return $this->find($column, $name) ?? $this->create($column, $name, $pid);
    }

    /**
     * @return array<int, string> manufacturer uid => name, of the manufacturers migrated from the column
     */
    public function names(string $column): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_migration_map');
        $queryBuilder->select('manufacturer.uid', 'manufacturer.name')
            ->from('tx_products_migration_map', 'map')
            ->innerJoin('map', 'tx_products_domain_model_manufacturer', 'manufacturer', 'manufacturer.uid = map.local_uid')
            ->where(
                $queryBuilder->expr()->eq('map.legacy_table', $queryBuilder->createNamedParameter($this->mapKey($column))),
                $queryBuilder->expr()->eq('map.local_table', $queryBuilder->createNamedParameter('tx_products_domain_model_manufacturer')),
                $queryBuilder->expr()->eq('manufacturer.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('manufacturer.uid');
        $result = $queryBuilder->executeQuery();
        $names = [];
        while ($row = $result->fetchAssociative()) {
            $names[(int)$row['uid']] = (string)$row['name'];
        }
        return $names;
    }

    /**
     * Fills the empty fields of the manufacturer; a field that already has a value keeps it.
     *
     * @param array<string, string> $values
     */
    public function complete(int $manufacturerUid, array $values): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_manufacturer');
        $row = $connection->select(array_keys($values), 'tx_products_domain_model_manufacturer', ['uid' => $manufacturerUid])->fetchAssociative();
        if ($row === false) {
            return;
        }
        $changes = array_filter(
            $values,
            static fn(string $value, string $field): bool => $value !== '' && (string)$row[$field] === '',
            ARRAY_FILTER_USE_BOTH
        );
        if ($changes !== []) {
            $connection->update('tx_products_domain_model_manufacturer', $changes, ['uid' => $manufacturerUid]);
        }
    }

    /**
     * The manufacturer fields a legacy tt_address record provides; empty when the record is gone.
     *
     * @return array<string, string>
     */
    public function legacyAddress(int $addressUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_address');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('address', 'zip', 'city', 'country', 'email', 'www')
            ->from('tt_address')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($addressUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return [];
        }
        return [
            'street' => trim((string)$row['address']),
            'zip' => trim((string)$row['zip']),
            'city' => trim((string)$row['city']),
            'country' => $this->countryCode(trim((string)$row['country'])),
            'email' => trim((string)$row['email']),
            'website' => trim((string)$row['www']),
        ];
    }

    /**
     * Pairs of names where one begins with the other, e.g. "Stelmach" and "Stelmach Trauringe": likely the
     * same manufacturer spelled twice, which only the shop owner can decide.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function lookAlikes(string $column): array
    {
        $names = array_values($this->names($column));
        $pairs = [];
        foreach ($names as $short) {
            foreach ($names as $long) {
                $shortKey = LegacyComparisonKey::of($short);
                $longKey = LegacyComparisonKey::of($long);
                if ($shortKey !== $longKey && str_starts_with($longKey, $shortKey)) {
                    $pairs[] = [$short, $long];
                }
            }
        }
        return $pairs;
    }

    private function create(string $column, string $name, int $pid): int
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_manufacturer');
        $now = (int)($GLOBALS['EXEC_TIME'] ?? time());
        $connection->insert('tx_products_domain_model_manufacturer', [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
            'name' => $name,
        ]);
        $uid = (int)$connection->lastInsertId();
        $this->migrationHelper->recordMapping($this->mapKey($column), $this->connectionPool->getConnectionForTable('tx_products_migration_map')->count('*', 'tx_products_migration_map', ['legacy_table' => $this->mapKey($column)]), 'tx_products_domain_model_manufacturer', $uid);
        return $uid;
    }

    private function countryCode(string $country): string
    {
        if (preg_match('/^[A-Za-z]{2}$/', $country) === 1) {
            return strtoupper($country);
        }
        foreach ($this->countryProvider->getAll() as $candidate) {
            if (strcasecmp($candidate->getName(), $country) === 0 || strcasecmp((string)$candidate->getOfficialName(), $country) === 0) {
                return $candidate->getAlpha2IsoCode();
            }
        }
        return '';
    }

    private function mapKey(string $column): string
    {
        return 'tt_products.' . $column . '.manufacturer';
    }
}
