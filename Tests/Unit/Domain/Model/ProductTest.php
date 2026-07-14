<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProductTest extends UnitTestCase
{
    #[Test]
    public function primaryImageIsNullWithoutImages(): void
    {
        $product = new Product();

        $this->assertNull($product->getPrimaryImage());
    }

    #[Test]
    public function primaryImageIsFirstOfGallery(): void
    {
        $product = new Product();
        /** @var ObjectStorage<FileReference> $images */
        $images = new ObjectStorage();
        $first = new FileReference();
        $images->attach($first);
        $images->attach(new FileReference());
        $product->setImages($images);

        $this->assertSame($first, $product->getPrimaryImage());
    }
}
