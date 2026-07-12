<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller\Backend;

use GoldeneZeiten\Products\Backend\CategoryAccessGuard;
use GoldeneZeiten\Products\Backend\CategoryMountResolver;
use GoldeneZeiten\Products\Backend\CategoryPermissionGuard;
use GoldeneZeiten\Products\Backend\CategoryTreeRepository;
use GoldeneZeiten\Products\Backend\Exception\ProductArchiveFailedException;
use GoldeneZeiten\Products\Backend\ProductArchiveService;
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
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend module for product management: tree view with relational listings.
 */
#[AsController]
final class ProductManagementModuleController
{
    private const TABLE_CATEGORY = 'tx_products_domain_model_category';
    private const TABLE_PRODUCT = 'tx_products_domain_model_product';
    private const TABLE_ARTICLE = 'tx_products_domain_model_article';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly CategoryTreeRepository $treeRepository,
        private readonly CategoryMountResolver $mountResolver,
        private readonly CategoryAccessGuard $accessGuard,
        private readonly CategoryPermissionGuard $permissionGuard,
        private readonly StorageFolderResolver $storageFolderResolver,
        private readonly ProductArchiveService $archiveService,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->translate('module.products_management.title'));

        if ($this->isArchiveRequest($request)) {
            if ($request->getMethod() === 'POST') {
                $this->handleArchivePost($request, $moduleTemplate);
            }
            $moduleTemplate->assignMultiple($this->buildArchiveView());
            return $moduleTemplate->renderResponse('Backend/ProductManagement/Archive');
        }

        $mounts = $this->mountResolver->resolveMountUids($this->getBackendUser());
        $articleUid = $this->resolveSelectedArticle($request, $mounts);
        $categoryUid = $articleUid > 0 ? 0 : $this->resolveSelectedCategory($request, $mounts);
        $productUid = ($articleUid > 0 || $categoryUid > 0) ? 0 : $this->resolveSelectedProduct($request, $mounts);
        $moduleTemplate->assignMultiple($this->buildViewData($request, $moduleTemplate, $categoryUid, $productUid, $articleUid));
        return $moduleTemplate->renderResponse('Backend/ProductManagement/Main');
    }

    private function isArchiveRequest(ServerRequestInterface $request): bool
    {
        return ($request->getQueryParams()['archive'] ?? null) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildArchiveView(): array
    {
        return [
            'backUrl' => (string)$this->uriBuilder->buildUriFromRoute('products_management', []),
        ];
    }

    private function handleArchivePost(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): void
    {
        $body = (array)$request->getParsedBody();
        try {
            $counts = $this->archiveService->archive(
                $this->storageFolderResolver->resolve(),
                (int)($body['destinationPid'] ?? 0),
                (int)($body['ageDays'] ?? 0),
            );
            $moduleTemplate->addFlashMessage($this->buildArchiveResultMessage($counts));
        } catch (ProductArchiveFailedException $exception) {
            $moduleTemplate->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private function buildArchiveResultMessage(array $counts): string
    {
        if ($counts === []) {
            return $this->translate('archive.message.nothing_moved');
        }
        $labels = [self::TABLE_PRODUCT => 'column.products', self::TABLE_ARTICLE => 'column.articles'];
        $parts = array_map(
            fn(string $table, int $count): string => sprintf('%d %s', $count, $this->translate($labels[$table])),
            array_keys($counts),
            array_values($counts),
        );
        return sprintf($this->translate('archive.message.moved'), implode(', ', $parts));
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
     * @param int[]|null $mounts
     */
    private function resolveSelectedArticle(ServerRequestInterface $request, ?array $mounts): int
    {
        $uid = (int)($request->getQueryParams()['article'] ?? 0);
        if ($uid <= 0) {
            return 0;
        }
        $article = $this->treeRepository->fetchArticleByUid($uid);
        if ($article === null || !$this->accessGuard->isProductAccessible($article['product'], $mounts)) {
            return 0;
        }
        return $uid;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(ServerRequestInterface $request, ModuleTemplate $moduleTemplate, int $categoryUid, int $productUid, int $articleUid): array
    {
        if ($articleUid > 0) {
            return $this->buildArticleScopedView($request, $articleUid);
        }
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
        $returnUrl = (string)$this->uriBuilder->buildUriFromRequest($request, ['product' => $productUid]);
        $product = $this->treeRepository->fetchProductByUid($productUid);
        $displayFields = $this->resolveDisplayFields();
        $rawValuesByUid = $this->treeRepository->fetchArticlesRawFieldsByProduct($productUid, $displayFields);
        $items = array_map(
            fn(array $article): array => $this->buildRow(
                self::TABLE_ARTICLE,
                $article,
                $returnUrl,
                $displayFields,
                $rawValuesByUid[$article['uid']] ?? [],
            ),
            $this->treeRepository->fetchArticlesByProduct($productUid)
        );
        return [
            'mode' => 'product',
            'selectedCategory' => null,
            'selectedProduct' => $product,
            'selectedArticle' => null,
            'itemsLabel' => $this->translate('column.articles'),
            'extraColumns' => $this->buildColumnHeaders(self::TABLE_ARTICLE, $displayFields),
            'items' => $items,
            'fields' => [],
            'columnSelector' => $this->buildColumnSelectorData(self::TABLE_ARTICLE, $returnUrl),
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
    private function buildArticleScopedView(ServerRequestInterface $request, int $articleUid): array
    {
        $returnUrl = (string)$this->uriBuilder->buildUriFromRequest($request, ['article' => $articleUid]);
        $article = $this->treeRepository->fetchArticleByUid($articleUid);
        $displayFields = $this->resolveDisplayFields();
        $rawValues = $this->treeRepository->fetchArticleRawFields($articleUid, $displayFields);
        return [
            'mode' => 'article',
            'selectedCategory' => null,
            'selectedProduct' => null,
            'selectedArticle' => $article,
            'itemsLabel' => '',
            'extraColumns' => [],
            'items' => [],
            'fields' => $this->buildFieldRows(self::TABLE_ARTICLE, $displayFields, $rawValues, $articleUid),
            'columnSelector' => $this->buildColumnSelectorData(self::TABLE_ARTICLE, $returnUrl),
            'actions' => [
                $this->buildEditAction('actions.edit_article', self::TABLE_ARTICLE, $articleUid, $returnUrl),
            ],
            'overviewHtml' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCategoryScopedView(ServerRequestInterface $request, int $categoryUid): array
    {
        $returnUrl = (string)$this->uriBuilder->buildUriFromRequest($request, ['category' => $categoryUid]);
        $category = $this->treeRepository->fetchCategoryByUid($categoryUid);
        $items = array_map(
            fn(array $product): array => $this->buildRow(self::TABLE_PRODUCT, $product, $returnUrl),
            $this->treeRepository->fetchProductsByCategory($categoryUid)
        );
        return [
            'mode' => 'category',
            'selectedCategory' => $category,
            'selectedProduct' => null,
            'selectedArticle' => null,
            'itemsLabel' => $this->translate('column.products'),
            'extraColumns' => [],
            'items' => $items,
            'fields' => [],
            'columnSelector' => null,
            'actions' => $this->buildCategoryScopedActions($categoryUid, $returnUrl),
            'overviewHtml' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryScopedActions(int $categoryUid, string $returnUrl): array
    {
        if (!$this->permissionGuard->isCategoryEditable($categoryUid, $this->getBackendUser())) {
            return [];
        }
        return [
            $this->buildAction('actions.new_subcategory', self::TABLE_CATEGORY, ['parent_category' => $categoryUid], $returnUrl),
            $this->buildAction('actions.new_product', self::TABLE_PRODUCT, ['categories' => $categoryUid], $returnUrl),
            $this->buildEditAction('actions.edit_category', self::TABLE_CATEGORY, $categoryUid, $returnUrl),
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
            'selectedArticle' => null,
            'itemsLabel' => '',
            'extraColumns' => [],
            'items' => [],
            'fields' => [],
            'columnSelector' => null,
            'actions' => [
                $this->buildAction('actions.new_category', self::TABLE_CATEGORY, ['parent_category' => 0], $returnUrl),
                ['label' => $this->translate('actions.archive_old_products'), 'url' => (string)$this->uriBuilder->buildUriFromRoute('products_management', ['archive' => 1])],
            ],
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
     * @param string[] $extraFields
     * @param array<string, mixed> $rawValues
     * @return array<string, mixed>
     */
    private function buildRow(string $table, array $item, string $returnUrl, array $extraFields = [], array $rawValues = []): array
    {
        return [
            'uid' => $item['uid'],
            'title' => $item['title'],
            'hidden' => $item['hidden'],
            'itemNumber' => $item['itemNumber'] ?? '',
            'editUrl' => $this->buildEditUrl($table, $item['uid'], $returnUrl),
            'extraValues' => array_map(
                fn(string $field): string => $this->formatFieldValue($table, $field, $rawValues[$field] ?? null, $item['uid']),
                $extraFields
            ),
        ];
    }

    /**
     * Editor-chosen extra columns for a table, read from the same $BE_USER->uc
     * slot ("list/displayFields") TYPO3's own record-list column selector
     * writes to - validated against real TCA columns, since that uc value is
     * attacker-reachable (a tampered POST to the core AJAX endpoint could
     * store arbitrary strings) and gets used to build a SQL SELECT list.
     * @return string[]
     */
    private function resolveDisplayFields(): array
    {
        $selected = $this->getBackendUser()->getModuleData('list/displayFields')[self::TABLE_ARTICLE] ?? [];
        if (!is_array($selected)) {
            return [];
        }
        $allowedFields = array_keys($GLOBALS['TCA'][self::TABLE_ARTICLE]['columns'] ?? []);
        return array_values(array_intersect($selected, $allowedFields));
    }

    /**
     * @param string[] $fields
     * @return string[]
     */
    private function buildColumnHeaders(string $table, array $fields): array
    {
        return array_map(
            fn(string $field): string => $this->getLanguageService()->sL(BackendUtility::getItemLabel($table, $field) ?? $field),
            $fields
        );
    }

    /**
     * @param string[] $fields
     * @param array<string, mixed> $rawValues
     * @return array<int, array{label: string, value: string}>
     */
    private function buildFieldRows(string $table, array $fields, array $rawValues, int $uid): array
    {
        return array_map(
            fn(string $field): array => [
                'label' => $this->getLanguageService()->sL(BackendUtility::getItemLabel($table, $field) ?? $field),
                'value' => $this->formatFieldValue($table, $field, $rawValues[$field] ?? null, $uid),
            ],
            $fields
        );
    }

    private function formatFieldValue(string $table, string $field, mixed $rawValue, int $uid): string
    {
        return (string)(BackendUtility::getProcessedValue($table, $field, $rawValue, 0, true, false, $uid) ?? '');
    }

    /**
     * Reuses TYPO3 core's own "Show columns" button/modal/AJAX endpoints
     * (TYPO3\CMS\Backend\Controller\ColumnSelectorController) as-is, so the
     * editor's column choice persists in $BE_USER->uc without this
     * extension building any settings UI or storage of its own.
     * @return array<string, string>
     */
    private function buildColumnSelectorData(string $table, string $returnUrl): array
    {
        $lang = $this->getLanguageService();
        return [
            'url' => (string)$this->uriBuilder->buildUriFromRoute('ajax_show_columns_selector', [
                'id' => $this->storageFolderResolver->resolve(),
                'table' => $table,
            ]),
            'target' => $returnUrl,
            'title' => sprintf(
                $lang->sL('LLL:EXT:backend/Resources/Private/Language/locallang_column_selector.xlf:showColumnsSelection'),
                $lang->sL($GLOBALS['TCA'][$table]['ctrl']['title'] ?? '') ?: $table,
            ),
            'buttonOk' => $lang->sL('LLL:EXT:backend/Resources/Private/Language/locallang_column_selector.xlf:updateColumnView'),
            'buttonClose' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.cancel'),
            'errorMessage' => $lang->sL('LLL:EXT:backend/Resources/Private/Language/locallang_column_selector.xlf:updateColumnView.error'),
            'label' => $lang->sL('LLL:EXT:backend/Resources/Private/Language/locallang_column_selector.xlf:showColumns'),
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
