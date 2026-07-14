<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Wishlist;

use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Guest wishlist storage in FE session. Logged-in users use tx_products_domain_model_wishlistitem.
 */
final class WishlistStorage
{
    private const SESSION_KEY = 'tx_products_wishlist';

    public function __construct(
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

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
        if ($this->requiresCookieConsent($request) && !$this->frontendUserResolver->hasConfirmedSessionCookie($request)) {
            return;
        }

        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode(array_values($productUids)));
        $frontendUser->storeSessionData();
    }

    private function requiresCookieConsent(ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof SiteInterface) {
            return false;
        }
        return (bool)$site->getSettings()->get('products.session.requireCookieConsent', false);
    }

    public function add(ServerRequestInterface $request, int $productUid): void
    {
        $productUids = $this->load($request);
        if (!in_array($productUid, $productUids, true)) {
            $productUids[] = $productUid;
        }
        $this->save($request, $productUids);
    }

    public function remove(ServerRequestInterface $request, int $productUid): void
    {
        $productUids = array_filter($this->load($request), static fn(int $uid): bool => $uid !== $productUid);
        $this->save($request, $productUids);
    }

    public function clear(ServerRequestInterface $request): void
    {
        $this->save($request, []);
    }

    public function moveUp(ServerRequestInterface $request, int $productUid): void
    {
        $this->swap($request, $productUid, -1);
    }

    public function moveDown(ServerRequestInterface $request, int $productUid): void
    {
        $this->swap($request, $productUid, 1);
    }

    private function swap(ServerRequestInterface $request, int $productUid, int $direction): void
    {
        $productUids = $this->load($request);
        $index = array_search($productUid, $productUids, true);
        if ($index === false) {
            return;
        }
        $swapIndex = (int)$index + $direction;
        $index = (int)$index;
        if (!isset($productUids[$swapIndex])) {
            return;
        }
        [$productUids[$index], $productUids[$swapIndex]] = [$productUids[$swapIndex], $productUids[$index]];
        $this->save($request, $productUids);
    }
}
