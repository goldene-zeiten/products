<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Tests\Unit\Configuration;

use GoldeneZeiten\Products\Watermark\Configuration\WatermarkConfigurationFactory;
use GoldeneZeiten\Products\Watermark\Configuration\WatermarkPosition;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class WatermarkConfigurationFactoryTest extends UnitTestCase
{
    #[Test]
    public function everySettingIsReadFromTheSite(): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['watermark' => [
            'file' => 'EXT:products_watermark/wm.png',
            'position' => 'top-left',
            'opacity' => 40,
            'scale' => 30,
            'minWidth' => 150,
            'margin' => 8,
        ]]]]);

        $configuration = (new WatermarkConfigurationFactory())->fromSite($site);

        $this->assertSame('EXT:products_watermark/wm.png', $configuration->file);
        $this->assertSame(WatermarkPosition::TopLeft, $configuration->position);
        $this->assertSame(40, $configuration->opacity);
        $this->assertSame(30, $configuration->scale);
        $this->assertSame(150, $configuration->minWidth);
        $this->assertSame(8, $configuration->margin);
        $this->assertTrue($configuration->isEnabled());
    }

    #[Test]
    public function anEmptyFileLeavesTheFeatureDisabled(): void
    {
        $configuration = (new WatermarkConfigurationFactory())->fromSite(new Site('products', 1, []));

        $this->assertSame('', $configuration->file);
        $this->assertFalse($configuration->isEnabled());
    }

    #[Test]
    public function anUnknownPositionFallsBackToBottomRight(): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['watermark' => [
            'file' => 'EXT:products_watermark/wm.png',
            'position' => 'nonsense',
        ]]]]);

        $configuration = (new WatermarkConfigurationFactory())->fromSite($site);

        $this->assertSame(WatermarkPosition::BottomRight, $configuration->position);
    }

    #[Test]
    public function outOfRangeValuesAreClamped(): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['watermark' => [
            'file' => 'EXT:products_watermark/wm.png',
            'opacity' => 400,
            'scale' => -20,
            'minWidth' => -5,
            'margin' => 999,
        ]]]]);

        $configuration = (new WatermarkConfigurationFactory())->fromSite($site);

        $this->assertSame(100, $configuration->opacity);
        $this->assertSame(0, $configuration->scale);
        $this->assertSame(0, $configuration->minWidth);
        $this->assertSame(100, $configuration->margin);
    }
}
