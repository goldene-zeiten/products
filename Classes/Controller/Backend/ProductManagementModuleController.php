<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller\Backend;

use GoldeneZeiten\Products\Backend\CategoryAccessGuard;
use GoldeneZeiten\Products\Backend\CategoryMountResolver;
use GoldeneZeiten\Products\Backend\CategoryTreeRepository;
use GoldeneZeiten\Products\Backend\StorageFolderResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * "Products" backend module: a category/product/article tree on the left, and either a
 * relational listing (products in a selected category, articles of a selected product) or
 * the plain core record list (nothing selected) on the right.
 */
#[AsController]
final class ProductManagementModuleController
{
    private const TABLE_CATEGORY = 'tx_products_domain_model_category';
    private const TABLE_PRODUCT = 'tx_products_domain_model_product';
    private const TABLE_ARTICLE = 'tx_products_domain_model_article';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly PageRenderer $pageRenderer,
        private readonly UriBuilder $uriBuilder,
        private readonly CategoryTreeRepository $treeRepository,
        private readonly CategoryMountResolver $mountResolver,
        private readonly CategoryAccessGuard $accessGuard,
        private readonly StorageFolderResolver $storageFolderResolver,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@goldene-zeiten/products/backend/category-tree.js');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:products/Resources/Private/Language/locallang_be.xlf', 'tree.');
        $mounts = $this->mountResolver->resolveMountUids($this->getBackendUser());
        $categoryUid = $this->resolveSelectedCategory($request, $mounts);
        $productUid = $categoryUid > 0 ? 0 : $this->resolveSelectedProduct($request, $mounts);

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->translate('module.products_management.title'));
        $moduleTemplate->assignMultiple([
            ...$this->buildViewData($request, $moduleTemplate, $categoryUid, $productUid),
            'newCategoryUrl' => $this->buildNewCategoryUrl($request),
        ]);
        return $moduleTemplate->renderResponse('Backend/ProductManagement/Main');
    }

    private function buildNewCategoryUrl(ServerRequestInterface $request): string
    {
        $returnUrl = (string)$this->uriBuilder->buildUriFromRequest($request, []);
        return $this->buildAction('tree.new_category', self::TABLE_CATEGORY, ['parent_category' => 0], $returnUrl)['url'];
    }

    /**
     * @param int[]|null $mounts
     */
    private function resolveSelectedCategory(ServerRequestInterface $request, ?array $mounts): int
    {
        $uid = (int)($request->getQueryParams()['category'] ?? 0);
        if ($uid <= 0 || !$this->treeRepository->categoryExists($uid) || !$this->accessGuard->isCategoryAccessible($uid, $mounts)) {
            return 0;
        }
        return $uid;
    }

    /**
     * @param int[]|null $mounts
     */
    private function resolveSelectedProduct(ServerRequestInterface $request, ?array $mounts): int
    {
        $uid = (int)($request->getQueryParams()['product'] ?? 0);
        if ($uid <= 0 || $this->treeRepository->fetchProductByUid($uid) === null || !$this->accessGuard->isProductAccessible($uid, $mounts)) {
            return 0;
        }
        return $uid;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(ServerRequestInterface $request, ModuleTemplate $moduleTemplate, int $categoryUid, int $productUid): array
    {
        if ($productUid > 0) {
            return $this->buildProductScopedView($request, $productUid);
        }
        if ($categoryUid > 0) {
            return $this->buildCategoryScopedView($request, $categoryUid);
        }
        return $this->buildOverviewView($request, $moduleTemplate);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductScopedView(ServerRequestInterface $request, int $productUid): array
    {
        $returnUrl = (string)$this->uriBuilder->buildUriFromRequest($request, []);
        $product = $this->treeRepository->fetchProductByUid($productUid);
        $items = array_map(
            fn(array $article): array => $this->buildRow(self::TABLE_ARTICLE, $article, $returnUrl),
            $this->treeRepository->fetchArticlesByProduct($productUid)
        );
        return [
            'mode' => 'product',
            'selectedCategory' => null,
            'selectedProduct' => $product,
            'itemsLabel' => $this->translate('column.articles'),
            'items' => $items,
            'actions' => [
                $this->buildAction('actions.new_article', self::TABLE_ARTICLE, ['product' => $productUid], $returnUrl),
                $this->buildEditAction('actions.edit_product', self::TABLE_PRODUCT, $productUid, $returnUrl),
            ],
            'overviewHtml' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCategoryScopedView(ServerRequestInterface $request, int $categoryUid): array
    {
        $returnUrl = (string)$this->uriBuilder->buildUriFromRequest($request, []);
        $category = $this->treeRepository->fetchCategoryByUid($categoryUid);
        $items = array_map(
            fn(array $product): array => $this->buildRow(self::TABLE_PRODUCT, $product, $returnUrl),
            $this->treeRepository->fetchProductsByCategory($categoryUid)
        );
        return [
            'mode' => 'category',
            'selectedCategory' => $category,
            'selectedProduct' => null,
            'itemsLabel' => $this->translate('column.products'),
            'items' => $items,
            'actions' => [
                $this->buildAction('actions.new_subcategory', self::TABLE_CATEGORY, ['parent_category' => $categoryUid], $returnUrl),
                $this->buildAction('actions.new_product', self::TABLE_PRODUCT, ['categories' => $categoryUid], $returnUrl),
                $this->buildEditAction('actions.edit_category', self::TABLE_CATEGORY, $categoryUid, $returnUrl),
            ],
            'overviewHtml' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverviewView(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): array
    {
        $returnUrl = (string)$this->uriBuilder->buildUriFromRequest($request, []);
        $pid = $this->storageFolderResolver->resolve();
        if ($pid <= 0) {
            $moduleTemplate->addFlashMessage($this->translate('message.no_storage_folder'), '', ContextualFeedbackSeverity::WARNING);
        }
        return [
            'mode' => 'overview',
            'selectedCategory' => null,
            'selectedProduct' => null,
            'itemsLabel' => '',
            'items' => [],
            'actions' => [$this->buildAction('actions.new_category', self::TABLE_CATEGORY, ['parent_category' => 0], $returnUrl)],
            'overviewHtml' => $pid > 0 ? $this->renderOverviewRecordList($request, $pid) : '',
        ];
    }

    private function renderOverviewRecordList(ServerRequestInterface $request, int $pid): string
    {
        $backendUser = $this->getBackendUser();
        $pageInfo = BackendUtility::readPageAccess($pid, $backendUser->getPagePermsClause(Permission::PAGE_SHOW)) ?: [];
        $dbList = GeneralUtility::makeInstance(DatabaseRecordList::class);
        $dbList->setRequest($request);
        $dbList->calcPerms = new Permission($backendUser->calcPerms($pageInfo));
        $dbList->pageRow = $pageInfo;
        $dbList->tableList = self::TABLE_PRODUCT;
        $dbList->start($pid, '', 0);
        return $dbList->generateList();
    }

    /**
     * @param array{uid: int, title: string, hidden: bool, itemNumber?: string} $item
     * @return array<string, mixed>
     */
    private function buildRow(string $table, array $item, string $returnUrl): array
    {
        return [
            'uid' => $item['uid'],
            'title' => $item['title'],
            'hidden' => $item['hidden'],
            'itemNumber' => $item['itemNumber'] ?? '',
            'editUrl' => $this->buildEditUrl($table, $item['uid'], $returnUrl),
        ];
    }

    /**
     * @param array<string, int> $defaultValues
     * @return array{label: string, url: string}
     */
    private function buildAction(string $labelKey, string $table, array $defaultValues, string $returnUrl): array
    {
        $pid = $this->storageFolderResolver->resolve();
        $url = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [$table => [$pid => 'new']],
            'defVals' => [$table => $defaultValues],
            'returnUrl' => $returnUrl,
        ]);
        return ['label' => $this->translate($labelKey), 'url' => $url];
    }

    /**
     * @return array{label: string, url: string}
     */
    private function buildEditAction(string $labelKey, string $table, int $uid, string $returnUrl): array
    {
        return ['label' => $this->translate($labelKey), 'url' => $this->buildEditUrl($table, $uid, $returnUrl)];
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:' . $key);
    }

    private function buildEditUrl(string $table, int $uid, string $returnUrl): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [$table => [$uid => 'edit']],
            'returnUrl' => $returnUrl,
        ]);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
