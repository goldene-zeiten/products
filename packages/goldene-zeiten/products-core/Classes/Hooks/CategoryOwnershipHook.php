<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Hooks;

use GoldeneZeiten\Products\Core\Backend\CategoryTreeRepository;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Maintains the page-like owner, group and permission fields of categories, which DataHandler itself
 * only ever writes for pages. A new category belongs to the user creating it and to that user's
 * first group. Afterwards an admin may change all permission fields, the owner the permissions and
 * the group (one of the owner's own groups), everybody else none of them.
 *
 * @internal Registered as a classic DataHandler hook in ext_localconf.php.
 *
 * Public, because TYPO3 instantiates a hook through makeInstance, which only injects dependencies
 * into a public service.
 */
#[Autoconfigure(public: true)]
final class CategoryOwnershipHook
{
    private const BITMASK_FIELDS = ['perms_user', 'perms_group', 'perms_everybody'];

    public function __construct(private readonly CategoryTreeRepository $treeRepository) {}

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, int|string $id, array &$fieldArray, DataHandler $dataHandler): void
    {
        if ($table !== 'tx_products_domain_model_category') {
            return;
        }
        $backendUser = $dataHandler->BE_USER;
        $incoming = $this->permittedFields($dataHandler->datamap[$table][$id] ?? [], $backendUser);
        if ($status === 'new') {
            $fieldArray['perms_userid'] = $this->incomingInt($incoming, 'perms_userid', $backendUser->isAdmin()) ?? (int)($backendUser->user['uid'] ?? 0);
            $fieldArray['perms_groupid'] = $this->allowedGroup($incoming, $backendUser) ?? (int)$backendUser->firstMainGroup;
            $fieldArray = array_replace($fieldArray, $this->incomingBitmasks($incoming));
            return;
        }
        $this->applyPermissionChanges((int)$id, $incoming, $fieldArray, $backendUser);
    }

    /**
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $fieldArray
     */
    private function applyPermissionChanges(int $uid, array $incoming, array &$fieldArray, BackendUserAuthentication $backendUser): void
    {
        $current = $this->treeRepository->fetchCategoryPermissionRow($uid);
        $isOwner = $current !== null && $current['perms_userid'] === (int)($backendUser->user['uid'] ?? 0);
        if (!$backendUser->isAdmin() && !$isOwner) {
            return;
        }
        $owner = $this->incomingInt($incoming, 'perms_userid', $backendUser->isAdmin());
        if ($owner !== null) {
            $fieldArray['perms_userid'] = $owner;
        }
        $group = $this->allowedGroup($incoming, $backendUser);
        if ($group !== null) {
            $fieldArray['perms_groupid'] = $group;
        }
        $fieldArray = array_replace($fieldArray, $this->incomingBitmasks($incoming));
    }

    /**
     * The permission fields are exclude fields; DataHandler's own exclude check does not apply here,
     * because it drops these fields for every table but pages anyway.
     *
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function permittedFields(array $incoming, BackendUserAuthentication $backendUser): array
    {
        if ($backendUser->isAdmin()) {
            return $incoming;
        }
        return array_filter(
            $incoming,
            static fn(string $field): bool => $backendUser->check('non_exclude_fields', 'tx_products_domain_model_category:' . $field),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param array<string, mixed> $incoming
     */
    private function incomingInt(array $incoming, string $field, bool $allowed): ?int
    {
        if (!$allowed || !array_key_exists($field, $incoming)) {
            return null;
        }
        $value = (int)$incoming[$field];
        return $value > 0 ? $value : null;
    }

    /**
     * @param array<string, mixed> $incoming
     */
    private function allowedGroup(array $incoming, BackendUserAuthentication $backendUser): ?int
    {
        $group = $this->incomingInt($incoming, 'perms_groupid', true);
        if ($group === null || (!$backendUser->isAdmin() && !in_array($group, $backendUser->userGroupsUID, true))) {
            return null;
        }
        return $group;
    }

    /**
     * @param array<string, mixed> $incoming
     * @return array<string, int>
     */
    private function incomingBitmasks(array $incoming): array
    {
        $bitmasks = [];
        foreach (self::BITMASK_FIELDS as $field) {
            if (!array_key_exists($field, $incoming)) {
                continue;
            }
            $value = (int)$incoming[$field];
            if ($value >= 0 && $value < 16) {
                $bitmasks[$field] = $value;
            }
        }
        return $bitmasks;
    }
}
