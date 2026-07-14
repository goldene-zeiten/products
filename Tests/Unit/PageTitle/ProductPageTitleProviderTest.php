<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\PageTitle;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\PageTitle\CurrentProductHolder;
use GoldeneZeiten\Products\Core\PageTitle\ProductPageTitleProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
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
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('', $provider->getTitle());
    }

    #[Test]
    public function titleModeReturnsTheProductTitle(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('title');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Gadget', $provider->getTitle());
    }

    #[Test]
    public function noneModeReturnsAnEmptyStringSoTheDefaultProviderTakesOver(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('none');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('', $provider->getTitle());
    }

    #[Test]
    public function subtitleOrTitleModePrefersTheSubtitleWhenPresent(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('subtitleOrTitle');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Deluxe Edition', $provider->getTitle());
    }

    #[Test]
    public function subtitleOrTitleModeFallsBackToTheTitleWithoutASubtitle(): void
    {
        $this->holder->setProduct($this->product('Widget', ''));

        $provider = $this->subject('subtitleOrTitle');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Widget', $provider->getTitle());
    }

    #[Test]
    public function titleAndSubtitleModeCombinesBoth(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('titleAndSubtitle');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Gadget - Deluxe Edition', $provider->getTitle());
    }

    #[Test]
    public function titleAndSubtitleModeFallsBackToJustTheTitleWithoutASubtitle(): void
    {
        $this->holder->setProduct($this->product('Widget', ''));

        $provider = $this->subject('titleAndSubtitle');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Widget', $provider->getTitle());
    }

    #[Test]
    public function subtitleAndTitleModeCombinesBothInReverseOrder(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('subtitleAndTitle');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Deluxe Edition - Gadget', $provider->getTitle());
    }

    #[Test]
    public function subtitleAndTitleModeFallsBackToJustTheTitleWithoutASubtitle(): void
    {
        $this->holder->setProduct($this->product('Widget', ''));

        $provider = $this->subject('subtitleAndTitle');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Widget', $provider->getTitle());
    }

    #[Test]
    public function anUnknownModeFallsBackToTheProductTitle(): void
    {
        $this->holder->setProduct($this->product('Gadget', 'Deluxe Edition'));

        $provider = $this->subject('not-a-real-mode');
        $provider->setRequest($this->createStub(ServerRequestInterface::class));
        $this->assertSame('Gadget', $provider->getTitle());
    }

    private function subject(string $mode): ProductPageTitleProvider
    {
        return new ProductPageTitleProvider($this->holder, $this->fakeConfigurationManager($mode));
    }

    private function fakeConfigurationManager(string $mode): ConfigurationManagerInterface
    {
        return new class ($mode) implements ConfigurationManagerInterface {
            public function __construct(private readonly string $mode) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['seo' => ['pageTitleMode' => $this->mode]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function product(string $title, string $subtitle): Product
    {
        $product = new Product();
        $product->setTitle($title);
        $product->setSubtitle($subtitle);
        return $product;
    }
}
