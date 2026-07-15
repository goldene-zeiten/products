<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Tests\Functional\Imaging;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Watermark\Configuration\WatermarkConfiguration;
use GoldeneZeiten\Products\Watermark\Configuration\WatermarkPosition;
use GoldeneZeiten\Products\Watermark\Imaging\WatermarkApplier;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class WatermarkApplierTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-watermark',
    ];

    private string $workingDirectory = '';
    private string $basePath = '';
    private string $watermarkPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->workingDirectory = Environment::getPublicPath() . '/typo3temp/assets/products_watermark_test';
        GeneralUtility::mkdir_deep($this->workingDirectory);
        $this->basePath = $this->workingDirectory . '/base.png';
        $this->watermarkPath = $this->workingDirectory . '/watermark.png';
        $this->createSolidImage($this->basePath, 200, 200, [255, 255, 255]);
        $this->createSolidImage($this->watermarkPath, 40, 40, [255, 0, 0]);
    }

    #[Test]
    public function opaqueWatermarkIsCompositedIntoTheConfiguredCorner(): void
    {
        $result = $this->subject()->apply($this->basePath, 200, 200, $this->configuration(WatermarkPosition::TopLeft, 100));

        self::assertColorIsRed($this->pixel($result, 5, 5));
        self::assertColorIsWhite($this->pixel($result, 150, 150));
    }

    #[Test]
    public function opacityBlendsTheWatermarkWithTheImage(): void
    {
        $result = $this->subject()->apply($this->basePath, 200, 200, $this->configuration(WatermarkPosition::TopLeft, 50));

        [$red, $green, $blue] = $this->pixel($result, 5, 5);
        $this->assertGreaterThan(200, $red, 'Red channel stays dominant.');
        $this->assertGreaterThan(90, $green, 'A half-transparent red over white lightens towards white.');
        $this->assertLessThan(210, $green, 'But it is not fully white either.');
        $this->assertSame($green, $blue, 'Green and blue stay balanced for a red-on-white blend.');
    }

    #[Test]
    public function centeredWatermarkLeavesTheCornersUntouched(): void
    {
        $result = $this->subject()->apply($this->basePath, 200, 200, $this->configuration(WatermarkPosition::Center, 100));

        self::assertColorIsRed($this->pixel($result, 100, 100));
        self::assertColorIsWhite($this->pixel($result, 5, 5));
    }

    #[Test]
    public function tiledWatermarkCoversTheWholeImage(): void
    {
        $result = $this->subject()->apply($this->basePath, 200, 200, $this->configuration(WatermarkPosition::Tile, 100));

        self::assertColorIsRed($this->pixel($result, 5, 5));
        self::assertColorIsRed($this->pixel($result, 165, 165));
    }

    private function subject(): WatermarkApplier
    {
        return $this->get(WatermarkApplier::class);
    }

    private function configuration(WatermarkPosition $position, int $opacity): WatermarkConfiguration
    {
        return new WatermarkConfiguration($this->watermarkPath, $position, $opacity, 0, 0, 0);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function createSolidImage(string $path, int $width, int $height, array $rgb): void
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        \assert($image instanceof \GdImage);
        $color = imagecolorallocate($image, max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
        imagefilledrectangle($image, 0, 0, $width, $height, (int)$color);
        imagepng($image, $path);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function pixel(string $path, int $x, int $y): array
    {
        $image = imagecreatefrompng($path);
        \assert($image instanceof \GdImage);
        $rgba = imagecolorat($image, $x, $y);

        return [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private static function assertColorIsRed(array $rgb): void
    {
        self::assertGreaterThan(200, $rgb[0], 'Red channel is high.');
        self::assertLessThan(60, $rgb[1], 'Green channel is low.');
        self::assertLessThan(60, $rgb[2], 'Blue channel is low.');
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private static function assertColorIsWhite(array $rgb): void
    {
        self::assertGreaterThan(240, $rgb[0]);
        self::assertGreaterThan(240, $rgb[1]);
        self::assertGreaterThan(240, $rgb[2]);
    }
}
