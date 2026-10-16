<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\PageTitle;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

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
    private string $pageTitleMode = 'title';

    public function __construct(
        private readonly CurrentProductHolder $currentProductHolder
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $site = $request->getAttribute('site');
        $this->pageTitleMode = $site instanceof SiteInterface
            ? (string)$site->getSettings()->get('products.seo.pageTitleMode', 'title')
            : 'title';
    }

    public function getTitle(): string
    {
        $product = $this->currentProductHolder->getProduct();
        if ($product === null || $this->pageTitleMode === 'none') {
            return '';
        }
        if ($this->currentProductHolder->getSeoTitle() !== '') {
            return $this->currentProductHolder->getSeoTitle();
        }

        return match ($this->pageTitleMode) {
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
