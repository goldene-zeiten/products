<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\PageTitle;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Substitutes the product's title/subtitle for the page title on a product single-view page.
 * Registered via `config.pageTitleProviders` in ext_localconf.php, ordered `before` core's
 * `record` provider so an empty string here falls through to the page's own title.
 *
 * Public, because TYPO3 instantiates page-title providers through makeInstance.
 */
#[Autoconfigure(public: true)]
final class ProductPageTitleProvider implements PageTitleProviderInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $settings = [];

    public function __construct(
        private readonly CurrentProductHolder $currentProductHolder,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->configurationManager->setRequest($request);
        $this->settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'ProductsCore'
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
