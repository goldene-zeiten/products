<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Core13\Routing;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TYPO3 13 has no native "route enhancers in site sets" support - that core feature
 * (Feature-107837) only landed in 14.1. This replicates it for any site depending on the
 * goldene-zeiten/products-core set by reading the very same route-enhancers.yaml the set ships
 * for 14.1+, so there is a single source of truth for both supported core versions.
 */
#[AsEventListener]
final class SiteSetRouteEnhancerListener
{
    private const SET_NAME = 'goldene-zeiten/products-core';
    private const ROUTE_ENHANCERS_FILE = 'EXT:products_core/Configuration/Sets/Products/route-enhancers.yaml';

    public function __construct(
        private readonly SetRegistry $setRegistry
    ) {}

    public function __invoke(SiteConfigurationLoadedEvent $event): void
    {
        $configuration = $event->getConfiguration();
        if (!$this->dependsOnProductsSet($configuration['dependencies'] ?? [])) {
            return;
        }

        $configuration['routeEnhancers'] = array_merge(
            $this->loadShippedRouteEnhancers(),
            $configuration['routeEnhancers'] ?? []
        );
        $event->setConfiguration($configuration);
    }

    /**
     * @param string[] $dependencies
     */
    private function dependsOnProductsSet(array $dependencies): bool
    {
        foreach ($this->setRegistry->getSets(...$dependencies) as $set) {
            if ($set->name === self::SET_NAME) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadShippedRouteEnhancers(): array
    {
        $file = GeneralUtility::getFileAbsFileName(self::ROUTE_ENHANCERS_FILE);
        $parsed = Yaml::parseFile($file);
        return $parsed['routeEnhancers'] ?? [];
    }
}
