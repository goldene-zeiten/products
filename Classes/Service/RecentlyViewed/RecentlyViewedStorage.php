<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\RecentlyViewed;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * FE session only, no DB - a capped FIFO list of viewed product uids. Viewing a product already in
 * the list moves it to the front rather than duplicating it.
 */
final class RecentlyViewedStorage
{
    private const SESSION_KEY = 'tx_products_recentlyViewed';
    private const DEFAULT_LIMIT = 10;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function record(ServerRequestInterface $request, int $productUid): void
    {
        $productUids = $this->withoutUid($this->load($request), $productUid);
        array_unshift($productUids, $productUid);
        $this->save($request, array_slice($productUids, 0, $this->limit()));
    }

    /**
     * @return int[]
     */
    public function load(ServerRequestInterface $request): array
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return [];
        }

        $data = $frontendUser->getKey('ses', self::SESSION_KEY);
        if (empty($data)) {
            return [];
        }

        $productUids = json_decode((string)$data, true);
        return is_array($productUids) ? array_map('intval', $productUids) : [];
    }

    /**
     * @param int[] $productUids
     */
    private function save(ServerRequestInterface $request, array $productUids): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication) {
            return;
        }

        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode(array_values($productUids)));
        $frontendUser->storeSessionData();
    }

    /**
     * @param int[] $productUids
     * @return int[]
     */
    private function withoutUid(array $productUids, int $productUid): array
    {
        return array_values(array_filter($productUids, static fn(int $uid): bool => $uid !== $productUid));
    }

    private function limit(): int
    {
        return max(1, (int)($this->settings['recentlyViewed']['limit'] ?? self::DEFAULT_LIMIT));
    }
}
