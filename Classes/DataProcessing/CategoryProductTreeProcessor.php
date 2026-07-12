<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\DataProcessing;

use GoldeneZeiten\Products\Domain\Dto\Category\CategoryTreeNode;
use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Category\CategoryTreeService;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Builds a category (sub-)tree with its products attached, rooted at a configurable entry-point category.
 *
 * TypoScript example:
 *   dataProcessing.10 = products-category-tree
 *   dataProcessing.10 {
 *       entryPointCategory = 12
 *       levels = 2
 *       as = categoryTree
 *       dataProcessing.10 = ...
 *   }
 */
#[AutoconfigureTag(
    name: 'data.processor',
    attributes: ['identifier' => 'products-category-tree'],
)]
final class CategoryProductTreeProcessor implements DataProcessorInterface
{
    private const DEFAULT_LEVELS = 1;
    private const DEFAULT_TITLE_FIELD = 'nav_title // title';
    private const DEFAULT_TARGET_VARIABLE_NAME = 'categoryTree';
    private const CATEGORY_TABLE = 'tx_products_domain_model_category';

    public function __construct(
        private readonly CategoryTreeService $categoryTreeService,
        private readonly ProductRepository $productRepository,
        private readonly ContentDataProcessor $contentDataProcessor,
    ) {}

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $entryPointCategoryUid = (int)$cObj->stdWrapValue('entryPointCategory', $processorConfiguration);
        if ($entryPointCategoryUid <= 0) {
            return $processedData;
        }
        $levels = (int)($cObj->stdWrapValue('levels', $processorConfiguration) ?: self::DEFAULT_LEVELS);
        $subtree = $this->categoryTreeService->getSubtree($entryPointCategoryUid, $levels);
        if ($subtree === []) {
            return $processedData;
        }

        $targetVariableName = (string)($cObj->stdWrapValue('as', $processorConfiguration) ?: self::DEFAULT_TARGET_VARIABLE_NAME);
        $processedData[$targetVariableName] = $this->buildRootNode($subtree[0], $cObj, $processorConfiguration);
        return $processedData;
    }

    /**
     * @param array<string, mixed> $processorConfiguration
     * @return array<string, mixed>
     */
    private function buildRootNode(CategoryTreeNode $rootNode, ContentObjectRenderer $cObj, array $processorConfiguration): array
    {
        $titleField = (string)($cObj->stdWrapValue('titleField', $processorConfiguration) ?: self::DEFAULT_TITLE_FIELD);
        $nestedDataProcessing = $processorConfiguration['dataProcessing.'] ?? [];
        $children = $this->limitChildren($rootNode->getChildren(), $cObj, $processorConfiguration);
        return $this->buildNode($rootNode, $children, $titleField, is_array($nestedDataProcessing) ? $nestedDataProcessing : []);
    }

    /**
     * @param CategoryTreeNode[] $children
     * @param array<string, mixed> $processorConfiguration
     * @return CategoryTreeNode[]
     */
    private function limitChildren(array $children, ContentObjectRenderer $cObj, array $processorConfiguration): array
    {
        $minItems = $cObj->stdWrapValue('minItems', $processorConfiguration);
        $maxItems = $cObj->stdWrapValue('maxItems', $processorConfiguration);
        if ($minItems !== '' && $minItems !== null && count($children) < (int)$minItems) {
            return [];
        }
        if ($maxItems !== '' && $maxItems !== null) {
            return array_slice($children, 0, (int)$maxItems);
        }
        return $children;
    }

    /**
     * @param CategoryTreeNode[] $children
     * @param array<int|string, mixed> $nestedDataProcessing
     * @return array<string, mixed>
     */
    private function buildNode(CategoryTreeNode $node, array $children, string $titleField, array $nestedDataProcessing): array
    {
        $category = $node->getCategory();
        $rawFields = $this->toRawFieldArray($category);
        $output = [
            'uid' => (int)$category->getUid(),
            'title' => $this->resolveTitle($category, $titleField),
            'slug' => $category->getSlug(),
            'slugPath' => $node->getSlugPath(),
            'depth' => $node->getDepth(),
            'discountPercent' => $category->getDiscountPercent(),
            'products' => $this->buildProducts($category),
            'children' => array_map(
                fn(CategoryTreeNode $child): array => $this->buildNode($child, $child->getChildren(), $titleField, $nestedDataProcessing),
                $children
            ),
            'data' => $rawFields,
        ];
        return $this->applyNestedDataProcessing($output, $rawFields, $nestedDataProcessing);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProducts(Category $category): array
    {
        return array_map(
            fn(Product $product): array => $this->buildProductOutput($product),
            $this->productRepository->findByCategory($category)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductOutput(Product $product): array
    {
        return [
            'uid' => (int)$product->getUid(),
            'title' => $product->getTitle(),
            'subtitle' => $product->getSubtitle(),
            'slug' => $product->getSlug(),
            'price' => $product->getPrice()->getDecimalString(),
            'data' => $this->toRawFieldArray($product),
        ];
    }

    /**
     * @param array<string, mixed> $output
     * @param array<string, mixed> $rawFields
     * @param array<int|string, mixed> $nestedDataProcessing
     * @return array<string, mixed>
     */
    private function applyNestedDataProcessing(array $output, array $rawFields, array $nestedDataProcessing): array
    {
        if ($nestedDataProcessing === []) {
            return $output;
        }
        $nodeCObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $nodeCObj->start($rawFields, self::CATEGORY_TABLE);
        return $this->contentDataProcessor->process($nodeCObj, ['dataProcessing.' => $nestedDataProcessing], $output);
    }

    /**
     * Resolves a "//"-separated field fallback chain (e.g. "nav_title // title"), same convention as
     * TYPO3's own menu titleField, so a future nav_title-style field can be added without touching this class.
     */
    private function resolveTitle(Category $category, string $titleFieldConfig): string
    {
        foreach (GeneralUtility::trimExplode('//', $titleFieldConfig, true) as $fieldName) {
            $getter = 'get' . ucfirst(GeneralUtility::underscoredToLowerCamelCase($fieldName));
            if (method_exists($category, $getter)) {
                $value = $category->$getter();
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }
        return $category->getTitle();
    }

    /**
     * @return array<string, scalar|null>
     */
    private function toRawFieldArray(AbstractEntity $entity): array
    {
        $raw = ['uid' => $entity->getUid(), 'pid' => $entity->getPid()];
        foreach ($entity->_getProperties() as $property => $value) {
            if (is_scalar($value) || $value === null) {
                $raw[GeneralUtility::camelCaseToLowerCaseUnderscored($property)] = $value;
            }
        }
        return $raw;
    }
}
