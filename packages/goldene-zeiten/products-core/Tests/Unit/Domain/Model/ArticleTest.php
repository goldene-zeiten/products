<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ArticleTest extends UnitTestCase
{
    #[Test]
    public function effectiveImagesFallsBackToProductGalleryWhenEmpty(): void
    {
        $product = new Product();
        /** @var ObjectStorage<FileReference> $productImages */
        $productImages = new ObjectStorage();
        $productImages->attach(new FileReference());
        $product->setImages($productImages);

        $article = new Article();
        $article->setProduct($product);

        $this->assertSame($productImages, $article->getEffectiveImages());
    }

    #[Test]
    public function effectiveImagesUsesOwnImagesWhenSet(): void
    {
        $product = new Product();
        /** @var ObjectStorage<FileReference> $productImages */
        $productImages = new ObjectStorage();
        $productImages->attach(new FileReference());
        $product->setImages($productImages);

        $article = new Article();
        $article->setProduct($product);
        /** @var ObjectStorage<FileReference> $ownImages */
        $ownImages = new ObjectStorage();
        $ownImages->attach(new FileReference());
        $article->setImages($ownImages);

        $this->assertSame($ownImages, $article->getEffectiveImages());
    }

    #[Test]
    public function effectiveImagesIsEmptyWithoutProductOrOwnImages(): void
    {
        $article = new Article();

        $this->assertCount(0, $article->getEffectiveImages());
    }
}
