<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Solr\Indexing;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Index Queue userFunc helpers that resolve product facet values which a flat TypoScript
 * SOLR_RELATION cannot express: the multi-hop attribute values (Product -> Article ->
 * AttributeValue -> Attribute) and the depth-prefixed category hierarchy paths that Solr's
 * hierarchy facet expects (0-/Root, 1-/Root/Child, ...).
 *
 * Each method is called as a USER cObject from the Index Queue field mapping; EXT:solr's indexer passes
 * the current product row via {@see self::setContentObjectRenderer()} (the public `$cObj` property that
 * older cores injected into was removed in TYPO3 v14 - Deprecation-94956). The returned list is split by
 * the field's SOLR_MULTIVALUE separator ("|").
 */
#[Autoconfigure(public: true)]
final class ProductIndexFieldMapper
{
    private ?ContentObjectRenderer $cObj = null;

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * @param array<string, mixed> $conf
     */
    #[AsAllowedCallable]
    public function attributeValues(string $content, array $conf): string
    {
        $productUid = (int)($this->cObj->data['uid'] ?? 0);
        if ($productUid === 0) {
            return '';
        }

        $values = [];
        foreach ($this->articleUids($productUid) as $articleUid) {
            foreach ($this->attributeValueLabels($articleUid) as $label) {
                $values[$label] = $label;
            }
        }

        return implode('|', array_values($values));
    }

    /**
     * @param array<string, mixed> $conf
     */
    #[AsAllowedCallable]
    public function categoryPaths(string $content, array $conf): string
    {
        $productUid = (int)($this->cObj->data['uid'] ?? 0);
        if ($productUid === 0) {
            return '';
        }

        $paths = [];
        foreach ($this->relatedUids('tx_products_product_category_mm', $productUid) as $categoryUid) {
            foreach ($this->hierarchyPaths($categoryUid) as $path) {
                $paths[$path] = $path;
            }
        }

        return implode('|', array_values($paths));
    }

    /**
     * @return int[]
     */
    private function articleUids(int $productUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_article');
        $result = $queryBuilder
            ->select('uid')
            ->from('tx_products_domain_model_article')
            ->where(
                $queryBuilder->expr()->eq(
                    'product',
                    $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)
                )
            )
            ->orderBy('sorting')
            ->executeQuery();
        $articleUids = [];
        while ($row = $result->fetchAssociative()) {
            $articleUids[] = (int)$row['uid'];
        }

        return $articleUids;
    }

    /**
     * @return string[] "Attribute: Value" labels for one article
     */
    private function attributeValueLabels(int $articleUid): array
    {
        // The visibility of both joined tables is spelled out, so a deleted or hidden value - or a value of a
        // deleted or hidden attribute - never becomes a facet, whatever restrictions the context would apply.
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_article_attributevalue_mm');
        $queryBuilder->getRestrictions()->removeAll();
        $expr = $queryBuilder->expr();
        $result = $queryBuilder
            ->select('value.value AS value', 'attribute.title AS attribute')
            ->from('tx_products_article_attributevalue_mm', 'mm')
            ->join('mm', 'tx_products_domain_model_attributevalue', 'value', (string)$expr->and(
                $expr->eq('value.uid', $queryBuilder->quoteIdentifier('mm.uid_foreign')),
                $expr->eq('value.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $expr->eq('value.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            ))
            ->leftJoin('value', 'tx_products_domain_model_attribute', 'attribute', (string)$expr->and(
                $expr->eq('attribute.uid', $queryBuilder->quoteIdentifier('value.attribute')),
                $expr->eq('attribute.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $expr->eq('attribute.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            ))
            ->where(
                $expr->eq('mm.uid_local', $queryBuilder->createNamedParameter($articleUid, Connection::PARAM_INT)),
                $expr->or(
                    $expr->eq('value.attribute', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    $expr->isNotNull('attribute.uid')
                )
            )
            ->orderBy('mm.sorting')
            ->executeQuery();

        $labels = [];
        while ($row = $result->fetchAssociative()) {
            $label = self::formatAttributeLabel((string)$row['attribute'], (string)$row['value']);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private static function formatAttributeLabel(string $attribute, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $attribute = trim($attribute);

        return $attribute === '' ? $value : $attribute . ': ' . $value;
    }

    /**
     * @return int[] uid_foreign values of an MM relation
     */
    private function relatedUids(string $mmTable, int $localUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($mmTable);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->select('uid_foreign')
            ->from($mmTable)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($localUid, Connection::PARAM_INT)
                )
            )
            ->orderBy('sorting')
            ->executeQuery();
        $relatedUids = [];
        while ($row = $result->fetchAssociative()) {
            $relatedUids[] = (int)$row['uid_foreign'];
        }

        return $relatedUids;
    }

    /**
     * The depth-prefixed prefixes of a category's ancestor chain, e.g. a leaf under Root/Child
     * yields ["0-/Root", "1-/Root/Child", "2-/Root/Child/Leaf"].
     *
     * @return string[]
     */
    private function hierarchyPaths(int $categoryUid): array
    {
        $titles = $this->ancestorTitles($categoryUid);
        $paths = [];
        $prefix = '';
        foreach ($titles as $depth => $title) {
            $prefix .= '/' . $title;
            $paths[] = $depth . '-' . $prefix;
        }

        return $paths;
    }

    /**
     * Category titles from the root down to the given category, or none at all when the category or one of
     * its ancestors is hidden or deleted. A hidden category is not shown in the shop, so neither it nor the
     * subtree beneath it is offered as a facet path; skipping just the hidden level instead would invent a
     * path the shop's category tree does not have.
     *
     * @return string[]
     */
    private function ancestorTitles(int $categoryUid): array
    {
        $titles = [];
        $guard = 0;
        while ($categoryUid > 0 && $guard < 100) {
            $row = $this->categoryRow($categoryUid);
            if ($row === null || (int)$row['hidden'] !== 0) {
                return [];
            }
            array_unshift($titles, str_replace('/', ' ', trim((string)$row['title'])));
            $categoryUid = (int)$row['parent_category'];
            $guard++;
        }

        return $titles;
    }

    /**
     * The category row whether hidden or not, so a hidden ancestor is recognised as such rather than
     * mistaken for the root; deleted categories are not found.
     *
     * @return array<string, mixed>|null
     */
    private function categoryRow(int $categoryUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_category');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder
            ->select('title', 'parent_category', 'hidden')
            ->from('tx_products_domain_model_category')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($categoryUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }
}
