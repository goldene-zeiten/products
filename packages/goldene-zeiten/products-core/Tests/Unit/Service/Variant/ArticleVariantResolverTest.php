<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Service\Variant;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\AttributeValue;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Service\Variant\ArticleVariantResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ArticleVariantResolverTest extends UnitTestCase
{
    private ArticleVariantResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ArticleVariantResolver();
    }

    #[Test]
    public function resolvesTheArticleMatchingTheExactCombination(): void
    {
        $small = $this->attributeValue(1);
        $red = $this->attributeValue(2);
        $large = $this->attributeValue(3);
        $smallRed = $this->articleWithValues([$small, $red]);
        $largeRed = $this->articleWithValues([$large, $red]);
        $product = $this->productWithArticles([$smallRed, $largeRed]);

        $resolved = $this->subject->resolve($product, [2, 1]);

        $this->assertSame($smallRed, $resolved);
    }

    #[Test]
    public function returnsNullWhenNoArticleMatches(): void
    {
        $product = $this->productWithArticles([$this->articleWithValues([$this->attributeValue(1)])]);

        $this->assertNull($this->subject->resolve($product, [999]));
    }

    #[Test]
    public function partialSelectionDoesNotMatch(): void
    {
        $article = $this->articleWithValues([$this->attributeValue(1), $this->attributeValue(2)]);
        $product = $this->productWithArticles([$article]);

        $this->assertNull($this->subject->resolve($product, [1]));
    }

    private function attributeValue(int $uid): AttributeValue
    {
        $value = new AttributeValue();
        $this->setUid($value, $uid);
        return $value;
    }

    /**
     * @param AttributeValue[] $values
     */
    private function articleWithValues(array $values): Article
    {
        $article = new Article();
        /** @var ObjectStorage<AttributeValue> $storage */
        $storage = new ObjectStorage();
        foreach ($values as $value) {
            $storage->attach($value);
        }
        $article->setAttributeValues($storage);
        return $article;
    }

    /**
     * @param Article[] $articles
     */
    private function productWithArticles(array $articles): Product
    {
        $product = new Product();
        /** @var ObjectStorage<Article> $storage */
        $storage = new ObjectStorage();
        foreach ($articles as $article) {
            $storage->attach($article);
        }
        $product->setArticles($storage);
        return $product;
    }

    private function setUid(object $entity, int $uid): void
    {
        $reflection = new \ReflectionProperty($entity, 'uid');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $uid);
    }
}
