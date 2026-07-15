<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller\Backend;

use GoldeneZeiten\Products\Core\Backend\CategoryAccessGuard;
use GoldeneZeiten\Products\Core\Backend\CategoryMountResolver;
use GoldeneZeiten\Products\Core\Backend\CategoryTreeRepository;
use GoldeneZeiten\Products\Core\Backend\StorageFolderResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Provides the JSON node data for the category/product/article backend tree.
 * Node identifiers are composite strings ("category-5", "product-12", "article-3")
 * since the tree spans three heterogeneous record types in one structure.
 *
 * @internal Backend AJAX endpoint, not part of any public API.
 */
#[AsController]
final class CategoryTreeController
{
    public function __construct(
        private readonly CategoryTreeRepository $treeRepository,
        private readonly CategoryMountResolver $mountResolver,
        private readonly CategoryAccessGuard $accessGuard,
        private readonly StorageFolderResolver $storageFolderResolver,
    ) {}

    /**
     * Provides the storage folder pid for new records created from the tree.
     */
    public function fetchConfigurationAction(): ResponseInterface
    {
        return new JsonResponse(['storageFolderPid' => $this->storageFolderResolver->resolve()]);
    }

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

    public function filterDataAction(ServerRequestInterface $request): ResponseInterface
    {
        $query = trim((string)($request->getQueryParams()['query'] ?? ''));
        if ($query === '') {
            return new JsonResponse([]);
        }
        $mounts = $this->mountResolver->resolveMountUids($this->getBackendUser());
        $matches = [
            ...$this->buildCategoryMatches($query, $mounts),
            ...$this->buildProductMatches($query, $mounts),
            ...$this->buildArticleMatches($query, $mounts),
        ];
        return new JsonResponse($matches);
    }

    public function reorderAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array)$request->getParsedBody();
        $identifier = $this->parseIdentifier((string)($params['identifier'] ?? ''));
        if ($identifier === null || $identifier['type'] === 'article') {
            return new JsonResponse([], 400);
        }
        $mounts = $this->mountResolver->resolveMountUids($this->getBackendUser());
        if (!$this->isAccessible($identifier['type'], $identifier['uid'], $mounts)) {
            return new JsonResponse([], 403);
        }
        $beforeUid = $this->resolveBeforeUid((string)($params['beforeIdentifier'] ?? ''), $identifier['type']);
        if ($identifier['type'] === 'category') {
            $this->treeRepository->reorderCategorySiblings($identifier['uid'], $beforeUid);
        } else {
            $this->treeRepository->reorderProducts($identifier['uid'], $beforeUid);
        }
        return new JsonResponse(['success' => true]);
    }

    private function resolveBeforeUid(string $beforeIdentifier, string $expectedType): ?int
    {
        $parsed = $this->parseIdentifier($beforeIdentifier);
        return $parsed !== null && $parsed['type'] === $expectedType ? $parsed['uid'] : null;
    }

    public function fetchRootlineAction(ServerRequestInterface $request): ResponseInterface
    {
        $identifier = $this->parseIdentifier((string)($request->getQueryParams()['identifier'] ?? ''));
        if ($identifier === null) {
            return new JsonResponse([], 400);
        }
        $mounts = $this->mountResolver->resolveMountUids($this->getBackendUser());
        if (!$this->isAccessible($identifier['type'], $identifier['uid'], $mounts)) {
            return new JsonResponse([], 403);
        }
        return new JsonResponse($this->buildAncestorIdentifiers($identifier['type'], $identifier['uid'], $mounts));
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
        if ($type === 'article') {
            $article = $this->treeRepository->fetchArticleByUid($uid);
            return $article !== null && $this->accessGuard->isProductAccessible($article['product'], $mounts);
        }
        return match ($type) {
            'category' => $this->accessGuard->isCategoryAccessible($uid, $mounts),
            'product' => $this->accessGuard->isProductAccessible($uid, $mounts),
            default => false,
        };
    }

    /**
     * @param int[]|null $mounts
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryMatches(string $query, ?array $mounts): array
    {
        $matches = [];
        foreach ($this->treeRepository->searchCategories($query) as $category) {
            if (!$this->accessGuard->isCategoryAccessible($category['uid'], $mounts)) {
                continue;
            }
            $ancestors = $this->buildAncestorIdentifiers('category', $category['uid'], $mounts);
            $parentIdentifier = $ancestors === [] ? 'root' : $ancestors[count($ancestors) - 1];
            $matches[] = [...$this->mapCategoryNode($category, $parentIdentifier), 'ancestors' => $ancestors];
        }
        return $matches;
    }

    /**
     * @param int[]|null $mounts
     * @return array<int, array<string, mixed>>
     */
    private function buildProductMatches(string $query, ?array $mounts): array
    {
        $matches = [];
        foreach ($this->treeRepository->searchProducts($query) as $product) {
            if (!$this->accessGuard->isProductAccessible($product['uid'], $mounts)) {
                continue;
            }
            $ancestors = $this->buildAncestorIdentifiers('product', $product['uid'], $mounts);
            $parentIdentifier = $ancestors === [] ? 'root' : $ancestors[count($ancestors) - 1];
            $matches[] = [...$this->mapProductNode($product, $parentIdentifier), 'ancestors' => $ancestors];
        }
        return $matches;
    }

    /**
     * @param int[]|null $mounts
     * @return array<int, array<string, mixed>>
     */
    private function buildArticleMatches(string $query, ?array $mounts): array
    {
        $matches = [];
        foreach ($this->treeRepository->searchArticles($query) as $article) {
            $productUid = $this->treeRepository->fetchArticleByUid($article['uid'])['product'] ?? 0;
            if (!$this->accessGuard->isProductAccessible($productUid, $mounts)) {
                continue;
            }
            $ancestors = $this->buildAncestorIdentifiers('article', $article['uid'], $mounts);
            $parentIdentifier = $ancestors === [] ? 'root' : $ancestors[count($ancestors) - 1];
            $matches[] = [...$this->mapArticleNode($article, $parentIdentifier), 'ancestors' => $ancestors];
        }
        return $matches;
    }

    /**
     * Composite identifiers from root to (excluding) the given node itself, root-first.
     * @param int[]|null $mounts
     * @return string[]
     */
    private function buildAncestorIdentifiers(string $type, int $uid, ?array $mounts): array
    {
        if ($type === 'category') {
            return array_map(static fn(int $c): string => 'category-' . $c, $this->treeRepository->fetchCategoryAncestorChain($uid));
        }
        if ($type === 'product') {
            $categoryUid = $this->treeRepository->fetchPrimaryCategoryUidOfProduct($uid, $mounts);
            if ($categoryUid === null) {
                return [];
            }
            $chain = $this->treeRepository->fetchCategoryAncestorChain($categoryUid);
            $chain[] = $categoryUid;
            return array_map(static fn(int $c): string => 'category-' . $c, $chain);
        }
        $article = $this->treeRepository->fetchArticleByUid($uid);
        if ($article === null) {
            return [];
        }
        return [...$this->buildAncestorIdentifiers('product', $article['product'], $mounts), 'product-' . $article['product']];
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
            'icon' => 'products-category',
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
            'icon' => 'products-product',
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
            'icon' => 'products-article',
            'hasChildren' => false,
        ];
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
