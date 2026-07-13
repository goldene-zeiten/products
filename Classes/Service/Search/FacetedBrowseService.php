<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Search;

use GoldeneZeiten\Products\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;

final class FacetedBrowseService
{
    private const ALLOWED_FIELDS = [
        'products' => ['title', 'itemNumber', 'crdate'],
        'articles' => ['title', 'itemNumber'],
        'categories' => ['title'],
    ];

    private const DEFAULT_FIELD = [
        'products' => 'title',
        'articles' => 'title',
        'categories' => 'title',
    ];

    private const LAST_ENTRIES_LIMIT = 10;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * @return array<string, object[]> group label => entities, insertion-ordered by group label
     */
    public function browse(string $mode, string $target, string $field): array
    {
        $resolvedField = $this->resolveField($target, $field);
        $entities = $this->entitiesFor($target);
        return match ($mode) {
            'firstletter' => $this->groupByFirstLetter($entities, $resolvedField),
            'year' => $this->groupByYear($entities, $resolvedField),
            'field' => $this->groupByValue($entities, $resolvedField),
            'lastentries' => ['' => $this->lastEntries($entities)],
            default => ['' => $entities],
        };
    }

    private function resolveField(string $target, string $field): string
    {
        $allowed = self::ALLOWED_FIELDS[$target] ?? self::ALLOWED_FIELDS['products'];
        return in_array($field, $allowed, true) ? $field : (self::DEFAULT_FIELD[$target] ?? 'title');
    }

    /**
     * @return object[]
     */
    private function entitiesFor(string $target): array
    {
        return match ($target) {
            'articles' => $this->articleRepository->findAllFlat(),
            'categories' => $this->categoryRepository->findAllIgnoringStoragePage(),
            default => $this->productRepository->findAllIgnoringStoragePage(),
        };
    }

    /**
     * @param object[] $entities
     * @return array<string, object[]>
     */
    private function groupByFirstLetter(array $entities, string $field): array
    {
        $groups = [];
        foreach ($entities as $entity) {
            $value = (string)$this->propertyValue($entity, $field);
            $letter = $value === '' ? '#' : mb_strtoupper(mb_substr($value, 0, 1));
            $groups[$letter][] = $entity;
        }
        ksort($groups);
        return $groups;
    }

    /**
     * @param object[] $entities
     * @return array<string, object[]>
     */
    private function groupByYear(array $entities, string $field): array
    {
        $groups = [];
        foreach ($entities as $entity) {
            $value = $this->propertyValue($entity, $field);
            $year = $value instanceof \DateTimeInterface ? $value->format('Y') : 'unknown';
            $groups[$year][] = $entity;
        }
        $keys = array_keys($groups);
        rsort($keys, SORT_STRING);
        /** @var array<string, object[]> $result */
        $result = [];
        foreach ($keys as $key) {
            /** @var string $key */
            $result[$key] = $groups[$key];
        }
        return $result;
    }

    /**
     * @param object[] $entities
     * @return array<string, object[]>
     */
    private function groupByValue(array $entities, string $field): array
    {
        $groups = [];
        foreach ($entities as $entity) {
            $value = (string)$this->propertyValue($entity, $field);
            $groups[$value][] = $entity;
        }
        ksort($groups);
        return $groups;
    }

    /**
     * @param object[] $entities
     * @return object[]
     */
    private function lastEntries(array $entities): array
    {
        $withCrdate = array_filter(
            $entities,
            fn(object $entity): bool => $this->propertyValue($entity, 'crdate') instanceof \DateTimeInterface
        );
        usort(
            $withCrdate,
            fn(object $a, object $b): int => $this->propertyValue($b, 'crdate') <=> $this->propertyValue($a, 'crdate')
        );
        return array_slice($withCrdate, 0, self::LAST_ENTRIES_LIMIT);
    }

    private function propertyValue(object $entity, string $field): mixed
    {
        $getter = 'get' . ucfirst($field);
        return method_exists($entity, $getter) ? $entity->$getter() : null;
    }
}
