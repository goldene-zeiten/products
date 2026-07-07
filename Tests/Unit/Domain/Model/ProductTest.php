<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Domain\Model;

use GoldeneZeiten\Products\Domain\Model\Product;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProductTest extends UnitTestCase
{
    /**
     * @test
     */
    public function primaryImageIsNullWithoutImages(): void
    {
        $product = new Product();

        self::assertNull($product->getPrimaryImage());
    }

    /**
     * @test
     */
    public function primaryImageIsFirstOfGallery(): void
    {
        $product = new Product();
        /** @var ObjectStorage<FileReference> $images */
        $images = new ObjectStorage();
        $first = new FileReference();
        $images->attach($first);
        $images->attach(new FileReference());
        $product->setImages($images);

        self::assertSame($first, $product->getPrimaryImage());
    }
}
