<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PageTitle;

use GoldeneZeiten\Products\Domain\Model\Product;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Substitutes the product's own title/subtitle for the page's default title on a product
 * single-view page, mirroring legacy tt_products' `substitutePagetitle` setting. Registered via
 * `config.pageTitleProviders` in ext_localconf.php (core's actual mechanism - not a DI tag; see
 * TYPO3\CMS\Core\PageTitle\PageTitleProviderManager, which resolves the configured provider FQCN
 * straight from the container, hence this class needing `public: true` in Services.yaml), ordered
 * `before` core's default `record` provider so returning an empty string here correctly falls
 * through to the page's own title on any non-product page.
 */
final class ProductPageTitleProvider implements PageTitleProviderInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly CurrentProductHolder $currentProductHolder,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function getTitle(): string
    {
        $product = $this->currentProductHolder->getProduct();
        if ($product === null) {
            return '';
        }

        return match ((string)($this->settings['seo']['pageTitleMode'] ?? 'title')) {
            'none' => '',
            'subtitleOrTitle' => $product->getSubtitle() !== '' ? $product->getSubtitle() : $product->getTitle(),
            'titleAndSubtitle' => $this->combine($product, $product->getTitle(), $product->getSubtitle()),
            'subtitleAndTitle' => $this->combine($product, $product->getSubtitle(), $product->getTitle()),
            default => $product->getTitle(),
        };
    }

    private function combine(Product $product, string $first, string $second): string
    {
        if ($product->getSubtitle() === '') {
            return $product->getTitle();
        }
        return $first . ' - ' . $second;
    }
}
