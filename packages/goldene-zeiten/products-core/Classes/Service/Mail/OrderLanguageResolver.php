<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Mail;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * The site language an order was placed in, for everything written to the customer about it afterwards: mails
 * are often sent outside a frontend request of that language - after the return from a payment provider, from
 * the backend or from a scheduler task.
 */
final class OrderLanguageResolver
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * The order's language, the site's default language when the order names none it knows; null without a site.
     */
    public function resolve(Order $order): ?SiteLanguage
    {
        $site = $this->site($order);
        if ($site === null) {
            return null;
        }
        foreach ($site->getLanguages() as $language) {
            if ($language->getLanguageId() === $order->getLanguageId()) {
                return $language;
            }
        }
        return $site->getDefaultLanguage();
    }

    /**
     * A frontend request in the order's site and language, for rendering mail templates.
     */
    public function request(Order $order): ?ServerRequestInterface
    {
        $site = $this->site($order);
        $language = $this->resolve($order);
        if ($site === null || $language === null) {
            return null;
        }
        return (new ServerRequest((string)$language->getBase()))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $language);
    }

    private function site(Order $order): ?Site
    {
        try {
            return $this->siteFinder->getSiteByIdentifier($order->getSiteIdentifier());
        } catch (SiteNotFoundException) {
            return null;
        }
    }
}
