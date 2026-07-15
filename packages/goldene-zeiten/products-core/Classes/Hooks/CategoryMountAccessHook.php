<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Hooks;

use GoldeneZeiten\Products\Core\Backend\CategoryAccessGuard;
use GoldeneZeiten\Products\Core\Backend\CategoryMountResolver;
use GoldeneZeiten\Products\Core\Backend\CategoryPermissionGuard;
use GoldeneZeiten\Products\Core\Backend\CategoryTreeRepository;
use GoldeneZeiten\Products\Core\Exception\CategoryAccessDeniedException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Enforces category-mount restrictions for every DataHandler write, regardless of entry point.
 *
 * @internal Registered as a classic DataHandler hook in ext_localconf.php.
 *
 * Public, because TYPO3 instantiates a hook through makeInstance, which only injects dependencies
 * into a public service.
 */
#[Autoconfigure(public: true)]
final class CategoryMountAccessHook
{
    private const TABLE_CATEGORY = 'tx_products_domain_model_category';
    private const TABLE_PRODUCT = 'tx_products_domain_model_product';
    private const TABLE_ARTICLE = 'tx_products_domain_model_article';

    public function __construct(
        private readonly CategoryAccessGuard $accessGuard,
        private readonly CategoryMountResolver $mountResolver,
        private readonly CategoryTreeRepository $treeRepository,
        private readonly CategoryPermissionGuard $permissionGuard,
    ) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function checkRecordUpdateAccess(string $table, int|string $id, array $incomingFieldArray, ?bool $currentAccess, DataHandler $dataHandler): ?bool
    {
        if (!$this->isManagedTable($table) || !is_int($id)) {
            return $currentAccess;
        }
        $mounts = $this->mountResolver->resolveMountUids($dataHandler->BE_USER);
        if ($mounts !== null && !$this->isRecordAccessible($table, $id, $mounts)) {
            return false;
        }
        if ($table === self::TABLE_CATEGORY && !$this->permissionGuard->isCategoryEditable($id, $dataHandler->BE_USER)) {
            return false;
        }
        return $currentAccess;
    }

    /**
     * @param mixed $value
     * @param mixed $pasteUpdate
     */
    public function processCmdmap(string $command, string $table, int|string $id, $value, bool &$commandIsProcessed, DataHandler $dataHandler, $pasteUpdate): void
    {
        if ($commandIsProcessed || !$this->isManagedTable($table) || !is_int($id)) {
            return;
        }
        $mounts = $this->mountResolver->resolveMountUids($dataHandler->BE_USER);
        if ($mounts !== null && !$this->isRecordAccessible($table, $id, $mounts)) {
            $this->denyCommand($command, $table, $id, $commandIsProcessed, $dataHandler, 'category mount restrictions');
            return;
        }
        if ($command === 'delete' && $table === self::TABLE_CATEGORY && !$this->permissionGuard->isCategoryDeletable($id, $dataHandler->BE_USER)) {
            $this->denyCommand($command, $table, $id, $commandIsProcessed, $dataHandler, 'insufficient category delete permission');
        }
    }

    private function denyCommand(string $command, string $table, int $id, bool &$commandIsProcessed, DataHandler $dataHandler, string $reason): void
    {
        $commandIsProcessed = true;
        $dataHandler->log(
            $table,
            $id,
            SystemLogDatabaseAction::UPDATE,
            null,
            SystemLogErrorClassification::USER_ERROR,
            'Attempt to "{command}" record {table}:{uid} denied by ' . $reason,
            null,
            ['command' => $command, 'table' => $table, 'uid' => $id]
        );
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, int|string $id, DataHandler $dataHandler): void
    {
        if (!$this->isManagedTable($table)) {
            return;
        }
        $mounts = $this->mountResolver->resolveMountUids($dataHandler->BE_USER);
        $accessible = $this->isTargetAccessible($table, $incomingFieldArray, $mounts);
        if ($accessible === null || $accessible) {
            return;
        }
        throw new CategoryAccessDeniedException(
            sprintf('New/edited category assignment for table %s is outside the current user\'s category mounts.', $table),
            1751800000
        );
    }

    private function isManagedTable(string $table): bool
    {
        return in_array($table, [self::TABLE_CATEGORY, self::TABLE_PRODUCT, self::TABLE_ARTICLE], true);
    }

    /**
     * @param int[] $mounts
     */
    private function isRecordAccessible(string $table, int $id, array $mounts): bool
    {
        return match ($table) {
            self::TABLE_CATEGORY => $this->accessGuard->isCategoryAccessible($id, $mounts),
            self::TABLE_PRODUCT => $this->accessGuard->isProductAccessible($id, $mounts),
            self::TABLE_ARTICLE => $this->isArticleAccessible($id, $mounts),
            default => true,
        };
    }

    /**
     * @param int[] $mounts
     */
    private function isArticleAccessible(int $articleUid, array $mounts): bool
    {
        $article = $this->treeRepository->fetchArticleByUid($articleUid);
        return $article === null || $this->accessGuard->isProductAccessible($article['product'], $mounts);
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @param int[]|null $mounts
     */
    private function isTargetAccessible(string $table, array $incomingFieldArray, ?array $mounts): ?bool
    {
        if ($mounts === null) {
            return true;
        }
        if ($table === self::TABLE_CATEGORY && array_key_exists('parent_category', $incomingFieldArray)) {
            $parent = (int)$incomingFieldArray['parent_category'];
            return $parent === 0 || $this->accessGuard->isCategoryAccessible($parent, $mounts);
        }
        if ($table === self::TABLE_PRODUCT && array_key_exists('categories', $incomingFieldArray)) {
            return $this->isAnyCategoryAccessible((string)$incomingFieldArray['categories'], $mounts);
        }
        if ($table === self::TABLE_ARTICLE && array_key_exists('product', $incomingFieldArray)) {
            return $this->accessGuard->isProductAccessible((int)$incomingFieldArray['product'], $mounts);
        }
        return null;
    }

    /**
     * @param int[] $mounts
     */
    private function isAnyCategoryAccessible(string $commaSeparatedUids, array $mounts): bool
    {
        $categoryUids = GeneralUtility::intExplode(',', $commaSeparatedUids, true);
        if ($categoryUids === []) {
            return true;
        }
        foreach ($categoryUids as $categoryUid) {
            if ($this->accessGuard->isCategoryAccessible($categoryUid, $mounts)) {
                return true;
            }
        }
        return false;
    }
}
