<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Tests\Functional\Resource;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Watermark\Resource\WatermarkableImageDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class WatermarkableImageDetectorTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-watermark',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/references.csv');
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function fileReferenceProvider(): array
    {
        return [
            'product image' => [100, true],
            'article image' => [101, true],
            'category image' => [102, true],
            'product download (wrong field)' => [103, false],
            'unrelated content image' => [104, false],
            'deleted reference' => [105, false],
            'file without any reference' => [999, false],
            'invalid uid' => [0, false],
        ];
    }

    #[Test]
    #[DataProvider('fileReferenceProvider')]
    public function detectsWhetherAFileIsAProductImage(int $fileUid, bool $expected): void
    {
        $subject = $this->get(WatermarkableImageDetector::class);

        $this->assertSame($expected, $subject->isWatermarkable($fileUid));
    }
}
