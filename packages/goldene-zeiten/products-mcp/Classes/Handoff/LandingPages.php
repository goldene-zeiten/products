<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Handoff;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * The pages a checkout link lands on: the checkout, or the basket page when the link cannot be used.
 */
final readonly class LandingPages
{
    /**
     * The checkout page; the basket page while no checkout page is set.
     */
    public function checkout(Site $site, ServerRequestInterface $request): string
    {
        $pageId = (int)$site->getSettings()->get('products.pids.checkoutPage', 0);

        return $pageId > 0 ? $this->url($site, $request, $pageId) : $this->basket($site, $request);
    }

    /**
     * The basket page; the root of the site while no basket page is set.
     */
    public function basket(Site $site, ServerRequestInterface $request): string
    {
        $pageId = (int)$site->getSettings()->get('products.pids.basketPage', 0);

        return $pageId > 0 ? $this->url($site, $request, $pageId) : '/';
    }

    private function url(Site $site, ServerRequestInterface $request, int $pageId): string
    {
        $language = $request->getAttribute('language');
        try {
            return (string)$site->getRouter()->generateUri(
                $pageId,
                $language instanceof SiteLanguage ? ['_language' => $language] : []
            );
        } catch (InvalidRouteArgumentsException) {
            return '/';
        }
    }
}
