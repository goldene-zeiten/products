<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\CategoryTreeReorderFailedException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Moves a category among its siblings, or a product in the product order, after a drag and drop in
 * the category tree. The move is a DataHandler command, so table and record permissions apply and
 * the change shows up in the history like an edit.
 */
final class CategoryTreeReorderService
{
    public function __construct(private readonly CategoryTreeRepository $treeRepository) {}

    /**
     * @throws CategoryTreeReorderFailedException
     */
    public function reorderCategory(int $uid, ?int $beforeUid, BackendUserAuthentication $backendUser): void
    {
        $parentUid = $this->treeRepository->fetchParentCategoryUid($uid) ?? 0;
        $siblingUids = array_column($this->treeRepository->fetchChildCategories($parentUid), 'uid');
        $this->move('tx_products_domain_model_category', $uid, $siblingUids, $beforeUid, $backendUser);
    }

    /**
     * @throws CategoryTreeReorderFailedException
     */
    public function reorderProduct(int $uid, ?int $beforeUid, BackendUserAuthentication $backendUser): void
    {
        $siblingUids = array_column($this->treeRepository->fetchAllProductsOrdered(), 'uid');
        $this->move('tx_products_domain_model_product', $uid, $siblingUids, $beforeUid, $backendUser);
    }

    /**
     * @param int[] $siblingUids current order including the moved record
     */
    private function move(string $table, int $uid, array $siblingUids, ?int $beforeUid, BackendUserAuthentication $backendUser): void
    {
        $target = $this->resolveMoveTarget($table, $uid, $siblingUids, $beforeUid);
        if ($target === null) {
            return;
        }
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [$table => [$uid => ['move' => $target]]], $backendUser);
        $dataHandler->process_cmdmap();
        if ($dataHandler->errorLog !== []) {
            throw new CategoryTreeReorderFailedException(implode(' ', $dataHandler->errorLog), 1791663460);
        }
    }

    /**
     * DataHandler's move target: the negative uid of the record to place the moved one after, or the
     * page uid to place it first on that page.
     *
     * @param int[] $siblingUids
     */
    private function resolveMoveTarget(string $table, int $uid, array $siblingUids, ?int $beforeUid): ?int
    {
        $others = array_values(array_filter($siblingUids, static fn(int $siblingUid): bool => $siblingUid !== $uid));
        if ($others === []) {
            return null;
        }
        $insertAt = $beforeUid === null ? false : array_search($beforeUid, $others, true);
        if ($insertAt === false) {
            return -$others[array_key_last($others)];
        }
        if ($insertAt === 0) {
            return $this->treeRepository->fetchPidOfRecord($table, $others[0]);
        }
        return -$others[$insertAt - 1];
    }
}
