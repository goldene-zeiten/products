<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller\Backend;

use GoldeneZeiten\Products\Backend\CategoryAccessGuard;
use GoldeneZeiten\Products\Backend\CategoryMountResolver;
use GoldeneZeiten\Products\Backend\CategoryTreeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Provides the JSON node data for the category/product/article backend tree.
 * Node identifiers are composite strings ("category-5", "product-12", "article-3")
 * since the tree spans three heterogeneous record types in one structure.
 *
 * @internal Backend AJAX endpoint, not part of any public API.
 */
final class CategoryTreeController
{
    public function __construct(
        private readonly CategoryTreeRepository $treeRepository,
        private readonly CategoryMountResolver $mountResolver,
        private readonly CategoryAccessGuard $accessGuard,
    ) {}

    public function fetchDataAction(ServerRequestInterface $request): ResponseInterface
    {
        $parent = (string)($request->getQueryParams()['parent'] ?? 'root');
        $mounts = $this->mountResolver->resolveMountUids($this->getBackendUser());

        if ($parent === '' || $parent === 'root') {
            return new JsonResponse($this->buildRootNodes($mounts));
        }

        $identifier = $this->parseIdentifier($parent);
        if ($identifier === null) {
            return new JsonResponse([], 400);
        }
        if (!$this->isAccessible($identifier['type'], $identifier['uid'], $mounts)) {
            return new JsonResponse([], 403);
        }

        return new JsonResponse($this->buildChildNodes($identifier['type'], $identifier['uid'], $parent));
    }

    /**
     * @param int[]|null $mounts
     * @return array<int, array<string, mixed>>
     */
    private function buildRootNodes(?array $mounts): array
    {
        $categories = $mounts === null ? $this->treeRepository->fetchRootCategories() : $this->treeRepository->fetchCategoriesByUids($mounts);
        return array_map(
            fn(array $category): array => $this->mapCategoryNode($category, 'root'),
            $categories
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChildNodes(string $type, int $uid, string $parentIdentifier): array
    {
        return match ($type) {
            'category' => [
                ...array_map(fn(array $c): array => $this->mapCategoryNode($c, $parentIdentifier), $this->treeRepository->fetchChildCategories($uid)),
                ...array_map(fn(array $p): array => $this->mapProductNode($p, $parentIdentifier), $this->treeRepository->fetchProductsByCategory($uid)),
            ],
            'product' => array_map(fn(array $a): array => $this->mapArticleNode($a, $parentIdentifier), $this->treeRepository->fetchArticlesByProduct($uid)),
            default => [],
        };
    }

    /**
     * @return array{type: string, uid: int}|null
     */
    private function parseIdentifier(string $identifier): ?array
    {
        if (!preg_match('/^(category|product|article)-(\d+)$/', $identifier, $matches)) {
            return null;
        }
        return ['type' => $matches[1], 'uid' => (int)$matches[2]];
    }

    /**
     * @param int[]|null $mounts
     */
    private function isAccessible(string $type, int $uid, ?array $mounts): bool
    {
        return match ($type) {
            'category' => $this->accessGuard->isCategoryAccessible($uid, $mounts),
            'product' => $this->accessGuard->isProductAccessible($uid, $mounts),
            default => false,
        };
    }

    /**
     * @param array{uid: int, title: string, hidden: bool} $category
     * @return array<string, mixed>
     */
    private function mapCategoryNode(array $category, string $parentIdentifier): array
    {
        return [
            'identifier' => 'category-' . $category['uid'],
            'parentIdentifier' => $parentIdentifier,
            'type' => 'category',
            'uid' => $category['uid'],
            'title' => $category['title'],
            'hidden' => $category['hidden'],
            'hasChildren' => $this->treeRepository->categoryHasChildren($category['uid']) || $this->treeRepository->fetchProductsByCategory($category['uid']) !== [],
        ];
    }

    /**
     * @param array{uid: int, title: string, hidden: bool, itemNumber: string} $product
     * @return array<string, mixed>
     */
    private function mapProductNode(array $product, string $parentIdentifier): array
    {
        return [
            'identifier' => 'product-' . $product['uid'],
            'parentIdentifier' => $parentIdentifier,
            'type' => 'product',
            'uid' => $product['uid'],
            'title' => $product['title'],
            'hidden' => $product['hidden'],
            'itemNumber' => $product['itemNumber'],
            'hasChildren' => $this->treeRepository->productHasArticles($product['uid']),
        ];
    }

    /**
     * @param array{uid: int, title: string, hidden: bool, itemNumber: string} $article
     * @return array<string, mixed>
     */
    private function mapArticleNode(array $article, string $parentIdentifier): array
    {
        return [
            'identifier' => 'article-' . $article['uid'],
            'parentIdentifier' => $parentIdentifier,
            'type' => 'article',
            'uid' => $article['uid'],
            'title' => $article['title'],
            'hidden' => $article['hidden'],
            'itemNumber' => $article['itemNumber'],
            'hasChildren' => false,
        ];
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
