<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Routing\Aspect;

use GoldeneZeiten\Products\Core\Routing\CategorySlugPathIndex;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Core\Routing\Aspect\PersistedMappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteAwareInterface;
use TYPO3\CMS\Core\Site\SiteLanguageAwareInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Routing aspect `ProductsCategoryPath`: maps a category uid to its full nested slug path of any
 * depth and back. The route variable it maps needs to accept slashes, e.g.:
 *
 *   routePath: '/{categoryPath}'
 *   requirements:
 *     categoryPath: '.+'
 *   aspects:
 *     categoryPath:
 *       type: ProductsCategoryPath
 */
#[Exclude]
final class CategoryPathMapper implements PersistedMappableAspectInterface, StaticMappableAspectInterface, SiteAwareInterface, SiteLanguageAwareInterface
{
    private Site $site;

    private SiteLanguage $siteLanguage;

    public function generate(string $value): ?string
    {
        if (!MathUtility::canBeInterpretedAsInteger($value)) {
            return null;
        }
        return $this->getIndex()->getPath((int)$value, $this->siteLanguage->getLanguageId());
    }

    public function resolve(string $value): ?string
    {
        $uid = $this->getIndex()->resolveUid($value, $this->siteLanguage->getLanguageId(), $this->site);
        return $uid === null ? null : (string)$uid;
    }

    public function setSite(Site $site): void
    {
        $this->site = $site;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSiteLanguage(SiteLanguage $siteLanguage): void
    {
        $this->siteLanguage = $siteLanguage;
    }

    public function getSiteLanguage(): SiteLanguage
    {
        return $this->siteLanguage;
    }

    private function getIndex(): CategorySlugPathIndex
    {
        // Core instantiates aspects with their settings as the only constructor argument, so the
        // index service cannot be constructor-injected here.
        return GeneralUtility::makeInstance(CategorySlugPathIndex::class);
    }
}
