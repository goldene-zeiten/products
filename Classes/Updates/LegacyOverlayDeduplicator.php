<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

/**
 * Legacy `*_language` overlay tables have no uniqueness constraint on (parent, sys_language_uid),
 * so duplicate translations must be resolved before migration: excluding deleted rows, a visible
 * (`hidden=0`) candidate always wins over a hidden one, and the highest uid wins within that group.
 */
final class LegacyOverlayDeduplicator
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{winners: array<int, array<string, mixed>>, losers: array<int, array<string, mixed>>}
     */
    public function deduplicate(array $rows, string $parentField): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if ((int)$row['deleted'] === 1) {
                continue;
            }
            $groups[$row[$parentField] . ':' . $row['sys_language_uid']][] = $row;
        }

        $winners = [];
        $losers = [];
        foreach ($groups as $candidates) {
            usort($candidates, self::compareCandidates(...));
            $winners[] = array_shift($candidates);
            array_push($losers, ...$candidates);
        }
        return ['winners' => $winners, 'losers' => $losers];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function compareCandidates(array $a, array $b): int
    {
        $hiddenComparison = (int)$a['hidden'] <=> (int)$b['hidden'];
        if ($hiddenComparison !== 0) {
            return $hiddenComparison;
        }
        return (int)$b['uid'] <=> (int)$a['uid'];
    }
}
