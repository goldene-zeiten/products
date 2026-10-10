<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Routing;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendGroupRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Maps a category uid to its full nested slug path ("parent/child/grandchild") and back, for any
 * depth. Categories flagged "hide_in_slug_path" contribute no segment, so such a category shares
 * its path with its closest visible ancestor and is never the result of resolving that path.
 *
 * Used by the CategoryPathMapper routing aspect, which runs before Extbase is bootstrapped and
 * therefore reads the category rows with a plain QueryBuilder.
 */
#[Autoconfigure(public: true)]
final class CategorySlugPathIndex
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
        private readonly SiteMatcher $siteMatcher,
        #[Autowire(service: 'cache.runtime')]
        private readonly FrontendInterface $runtimeCache,
    ) {}

    /**
     * The slug path of the category, or null when it does not exist or has no visible segment.
     */
    public function getPath(int $categoryUid, int $languageId): ?string
    {
        $path = $this->getEntries($languageId)[$categoryUid]['path'] ?? '';
        return $path === '' ? null : $path;
    }

    /**
     * The default-language uid of the category in $site whose own segment ends the given path.
     */
    public function resolveUid(string $path, int $languageId, Site $site): ?int
    {
        $path = trim($path, '/');
        foreach ($this->getEntries($languageId) as $uid => $entry) {
            if ($entry['path'] === $path && !$entry['hideInSlugPath'] && $this->isContainedInSite($entry['pid'], $site)) {
                return $uid;
            }
        }
        return null;
    }

    /**
     * @return array<int, array{pid: int, path: string, hideInSlugPath: bool}>
     */
    private function getEntries(int $languageId): array
    {
        $cacheIdentifier = 'products_core_category_slug_paths_' . $languageId;
        $entries = $this->runtimeCache->get($cacheIdentifier);
        if (!is_array($entries)) {
            $entries = $this->buildEntries($this->fetchCategories($languageId));
            $this->runtimeCache->set($cacheIdentifier, $entries);
        }
        return $entries;
    }

    /**
     * @param array<int, array{pid: int, parent: int, segment: string, hideInSlugPath: bool}> $categories
     * @return array<int, array{pid: int, path: string, hideInSlugPath: bool}>
     */
    private function buildEntries(array $categories): array
    {
        $entries = [];
        foreach ($categories as $uid => $category) {
            $entries[$uid] = [
                'pid' => $category['pid'],
                'path' => implode('/', $this->collectSegments($uid, $categories)),
                'hideInSlugPath' => $category['hideInSlugPath'],
            ];
        }
        return $entries;
    }

    /**
     * Root-first segments of the category and its ancestors; a cyclic parent chain stops at the repetition.
     *
     * @param array<int, array{pid: int, parent: int, segment: string, hideInSlugPath: bool}> $categories
     * @return string[]
     */
    private function collectSegments(int $uid, array $categories): array
    {
        $segments = [];
        $visited = [];
        while (isset($categories[$uid]) && !isset($visited[$uid])) {
            $visited[$uid] = true;
            if (!$categories[$uid]['hideInSlugPath'] && $categories[$uid]['segment'] !== '') {
                array_unshift($segments, $categories[$uid]['segment']);
            }
            $uid = $categories[$uid]['parent'];
        }
        return $segments;
    }

    /**
     * Default-language categories keyed by uid, their segment taken from the translation in $languageId if any.
     *
     * @return array<int, array{pid: int, parent: int, segment: string, hideInSlugPath: bool}>
     */
    private function fetchCategories(int $languageId): array
    {
        $categories = [];
        $translatedSegments = [];
        $result = $this->createCategoryQuery($languageId)->executeQuery();
        while ($row = $result->fetchAssociative()) {
            if ((int)$row['sys_language_uid'] > 0) {
                $translatedSegments[(int)$row['l10n_parent']] = $this->ownSegment((string)$row['slug']);
                continue;
            }
            $categories[(int)$row['uid']] = [
                'pid' => (int)$row['pid'],
                'parent' => (int)$row['parent_category'],
                'segment' => $this->ownSegment((string)$row['slug']),
                'hideInSlugPath' => (bool)$row['hide_in_slug_path'],
            ];
        }
        foreach (array_intersect_key($translatedSegments, $categories) as $uid => $segment) {
            $categories[$uid] = ['segment' => $segment] + $categories[$uid];
        }
        return $categories;
    }

    private function createCategoryQuery(int $languageId): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_category');
        $queryBuilder->setRestrictions(new FrontendRestrictionContainer($this->context));
        // Frontend groups are not known yet while routing, as in core's PersistedAliasMapper.
        $queryBuilder->getRestrictions()->removeByType(FrontendGroupRestriction::class);
        return $queryBuilder
            ->select('uid', 'pid', 'slug', 'parent_category', 'hide_in_slug_path', 'sys_language_uid', 'l10n_parent')
            ->from('tx_products_domain_model_category')
            ->where($queryBuilder->expr()->in(
                'sys_language_uid',
                $queryBuilder->createNamedParameter(array_unique([-1, 0, $languageId]), Connection::PARAM_INT_ARRAY)
            ));
    }

    private function ownSegment(string $storedSlug): string
    {
        $segments = explode('/', trim($storedSlug, '/'));
        return (string)end($segments);
    }

    private function isContainedInSite(int $pageId, Site $site): bool
    {
        try {
            return $this->siteMatcher->matchByPageId($pageId)->getRootPageId() === $site->getRootPageId();
        } catch (SiteNotFoundException) {
            // A storage folder outside any site belongs to every site, as for core's uniqueInSite slugs.
            return true;
        }
    }
}
