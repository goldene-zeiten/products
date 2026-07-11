<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Wishlist;

use GoldeneZeiten\Products\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Guest (not-logged-in) wishlist storage: a plain list of product uids in the FE session, same
 * shape discipline as BasketStorage. Only ever used for guests - an identified shopper's wishlist
 * lives in tx_products_domain_model_wishlistitem instead, see WishlistService.
 */
final class WishlistStorage
{
    private const SESSION_KEY = 'tx_products_wishlist';

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly FrontendUserResolver $frontendUserResolver,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
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
        if ($this->requiresCookieConsent() && !$this->frontendUserResolver->hasConfirmedSessionCookie($request)) {
            return;
        }

        $frontendUser->setKey('ses', self::SESSION_KEY, json_encode(array_values($productUids)));
        $frontendUser->storeSessionData();
    }

    private function requiresCookieConsent(): bool
    {
        return (bool)($this->settings['session']['requireCookieConsent'] ?? false);
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
