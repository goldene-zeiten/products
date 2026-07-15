<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Configuration;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Builds a {@see WatermarkConfiguration} from a site's settings.
 *
 * This is the single place that knows the settings keys and their source, keeping every consuming
 * service free of request/site awareness.
 */
final readonly class WatermarkConfigurationFactory
{
    public function fromSite(Site $site): WatermarkConfiguration
    {
        $settings = $site->getSettings();

        return new WatermarkConfiguration(
            file: trim((string)$settings->get('products.watermark.file', '')),
            position: WatermarkPosition::fromSetting((string)$settings->get('products.watermark.position', 'bottom-right')),
            opacity: MathUtility::forceIntegerInRange((int)$settings->get('products.watermark.opacity', 50), 0, 100),
            scale: MathUtility::forceIntegerInRange((int)$settings->get('products.watermark.scale', 25), 0, 100),
            minWidth: max(0, (int)$settings->get('products.watermark.minWidth', 200)),
            margin: MathUtility::forceIntegerInRange((int)$settings->get('products.watermark.margin', 5), 0, 100),
        );
    }
}
