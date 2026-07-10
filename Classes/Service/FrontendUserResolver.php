<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use Doctrine\DBAL\ArrayParameterType;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class FrontendUserResolver
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function getUid(ServerRequestInterface $request): int
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication && ($frontendUser->user['uid'] ?? 0) > 0) {
            return (int)$frontendUser->user['uid'];
        }

        return 0;
    }

    /**
     * Whether the visitor's browser already carries the FE-session cookie from an earlier request
     * - never true for the request that would be the first to set it, matching legacy's
     * `isCookieSet()`-gated `writeSession()` (a session write never itself counts as consent).
     */
    public function hasConfirmedSessionCookie(ServerRequestInterface $request): bool
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return false;
        }

        return isset($request->getCookieParams()[$frontendUser->name]);
    }

    /**
     * Never stacked - a shopper in several discounted groups (or with both a personal and a
     * group discount) gets the single best rate, not their sum, matching legacy's behaviour.
     */
    public function getDiscountPercent(ServerRequestInterface $request): float
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication || ($frontendUser->user['uid'] ?? 0) <= 0) {
            return 0.0;
        }

        $discounts = [(float)($frontendUser->user['tx_products_discount_percent'] ?? 0.0)];
        $groupUids = GeneralUtility::intExplode(',', (string)($frontendUser->user['usergroup'] ?? ''), true);
        foreach ($this->fetchGroupDiscounts($groupUids) as $groupDiscount) {
            $discounts[] = (float)$groupDiscount;
        }

        return max($discounts);
    }

    /**
     * fe_groups is not Extbase-domain-modeled, same convention as CategoryMountResolver's plain
     * QueryBuilder lookup against be_groups.
     *
     * @param int[] $groupUids
     * @return string[]
     */
    private function fetchGroupDiscounts(array $groupUids): array
    {
        if ($groupUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_groups');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('tx_products_discount_percent')
            ->from('fe_groups')
            ->where($queryBuilder->expr()->in(
                'uid',
                $queryBuilder->createNamedParameter($groupUids, ArrayParameterType::INTEGER)
            ))
            ->executeQuery()
            ->fetchFirstColumn();
    }
}
