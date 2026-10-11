<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\PageTitle;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\PageTitle\CurrentProductHolder;
use GoldeneZeiten\Products\Core\PageTitle\ProductPageTitleProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProductPageTitleProviderTest extends UnitTestCase
{
    private CurrentProductHolder $holder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->holder = new CurrentProductHolder();
    }

    #[Test]
    public function returnsAnEmptyStringWithoutACurrentProduct(): void
    {
        $provider = $this->subject('title');
        $this->assertSame('', $provider->getTitle());
    }

    #[Test]
    public function titleModeReturnsTheProductTitle(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('title');
        $this->assertSame('Gadget', $provider->getTitle());
    }

    #[Test]
    public function noneModeReturnsAnEmptyStringSoTheDefaultProviderTakesOver(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('none');
        $this->assertSame('', $provider->getTitle());
    }

    #[Test]
    public function subtitleOrTitleModePrefersTheSubtitleWhenPresent(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('subtitleOrTitle');
        $this->assertSame('Deluxe Edition', $provider->getTitle());
    }

    #[Test]
    public function subtitleOrTitleModeFallsBackToTheTitleWithoutASubtitle(): void
    {
        $this->holder->setProduct($this->product('Widget', ''));

        $provider = $this->subject('subtitleOrTitle');
        $this->assertSame('Widget', $provider->getTitle());
    }

    #[Test]
    public function titleAndSubtitleModeCombinesBoth(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('titleAndSubtitle');
        $this->assertSame('Gadget - Deluxe Edition', $provider->getTitle());
    }

    #[Test]
    public function titleAndSubtitleModeFallsBackToJustTheTitleWithoutASubtitle(): void
    {
        $this->holder->setProduct($this->product('Widget', ''));

        $provider = $this->subject('titleAndSubtitle');
        $this->assertSame('Widget', $provider->getTitle());
    }

    #[Test]
    public function subtitleAndTitleModeCombinesBothInReverseOrder(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('subtitleAndTitle');
        $this->assertSame('Deluxe Edition - Gadget', $provider->getTitle());
    }

    #[Test]
    public function subtitleAndTitleModeFallsBackToJustTheTitleWithoutASubtitle(): void
    {
        $this->holder->setProduct($this->product('Widget', ''));

        $provider = $this->subject('subtitleAndTitle');
        $this->assertSame('Widget', $provider->getTitle());
    }

    #[Test]
    public function anUnknownModeFallsBackToTheProductTitle(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('not-a-real-mode');
        $this->assertSame('Gadget', $provider->getTitle());
    }

    private function subject(string $mode): ProductPageTitleProvider
    {
        $provider = new ProductPageTitleProvider($this->holder);
        $site = new Site('products', 1, ['settings' => ['products' => ['seo' => ['pageTitleMode' => $mode]]]]);
        $provider->setRequest((new ServerRequest('http://localhost/'))->withAttribute('site', $site));

        return $provider;
    }

    private function product(string $title, string $subtitle): Product
    {
        $product = new Product();
        $product->setTitle($title);
        $product->setSubtitle($subtitle);
        return $product;
    }
}
