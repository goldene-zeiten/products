<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

/**
 * Builds the absolute prefilled-withdrawal-form URL that order mails carry as their withdrawal
 * button. Mails are also sent outside a frontend request (payment webhooks), so the URL comes from
 * the order's site router rather than Extbase's UriBuilder - which means the cHash the frontend
 * will demand for the plugin arguments has to be computed and appended here.
 */
final class WithdrawalUrlFactory
{
    private const PLUGIN_NAMESPACE = 'tx_productscore_withdrawal';

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly CacheHashCalculator $cacheHashCalculator
    ) {}

    /**
     * The address of the online withdrawal function itself, without any order; empty when the order's
     * site no longer exists or has no withdrawal page configured.
     */
    public function createFunctionUrlFor(Order $order): string
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($order->getSiteIdentifier());
        } catch (SiteNotFoundException) {
            return '';
        }
        $pageId = (int)$site->getSettings()->get('products.pids.withdrawalPage', 0);
        return $pageId === 0 ? '' : (string)$site->getRouter()->generateUri($pageId);
    }

    /**
     * Null when the order's site no longer exists or no withdrawal page is configured - the mail
     * then simply renders without the button.
     */
    public function createFor(Order $order): ?string
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($order->getSiteIdentifier());
        } catch (SiteNotFoundException) {
            return null;
        }
        $pageId = (int)$site->getSettings()->get('products.pids.withdrawalPage', 0);
        if ($pageId === 0 || $order->getUid() === null) {
            return null;
        }
        $parameters = [
            self::PLUGIN_NAMESPACE => [
                'order' => $order->getUid(),
                'hash' => $this->withdrawalTokenService->generateToken($order),
            ],
        ];
        $uri = $site->getRouter()->generateUri($pageId, $parameters);
        $cacheHash = $this->cacheHashCalculator->generateForParameters(
            HttpUtility::buildQueryString(array_merge(['id' => $pageId], $parameters))
        );
        return (string)$uri . ($uri->getQuery() === '' ? '?' : '&') . 'cHash=' . $cacheHash;
    }
}
