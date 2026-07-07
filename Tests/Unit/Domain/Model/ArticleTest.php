<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Domain\Model;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ArticleTest extends UnitTestCase
{
    /**
     * @test
     */
    public function effectiveImagesFallsBackToProductGalleryWhenEmpty(): void
    {
        $product = new Product();
        /** @var ObjectStorage<FileReference> $productImages */
        $productImages = new ObjectStorage();
        $productImages->attach(new FileReference());
        $product->setImages($productImages);

        $article = new Article();
        $article->setProduct($product);

        self::assertSame($productImages, $article->getEffectiveImages());
    }

    /**
     * @test
     */
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

        self::assertSame($ownImages, $article->getEffectiveImages());
    }

    /**
     * @test
     */
    public function effectiveImagesIsEmptyWithoutProductOrOwnImages(): void
    {
        $article = new Article();

        self::assertCount(0, $article->getEffectiveImages());
    }
}
