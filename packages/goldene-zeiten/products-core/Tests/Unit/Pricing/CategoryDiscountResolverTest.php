<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Pricing\CategoryDiscountResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CategoryDiscountResolverTest extends UnitTestCase
{
    private CategoryDiscountResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new CategoryDiscountResolver();
    }

    #[Test]
    public function noDiscountAnywhereReturnsZeroInBothModes(): void
    {
        $product = $this->productInCategory($this->category(0.0));

        $this->assertSame(0.0, $this->subject->getDiscountPercent($product, 'maxAcrossTree'));
        $this->assertSame(0.0, $this->subject->getDiscountPercent($product, 'nearestCategory'));
    }

    #[Test]
    public function productOwnDiscountIsUsedDirectlyInBothModes(): void
    {
        $product = $this->productInCategory($this->category(5.0));
        $product->setDiscountPercent(15.0);

        $this->assertSame(15.0, $this->subject->getDiscountPercent($product, 'maxAcrossTree'));
        $this->assertSame(15.0, $this->subject->getDiscountPercent($product, 'nearestCategory'));
    }

    #[Test]
    public function productDiscountDisabledZeroesTheResultRegardlessOfCategoryDiscount(): void
    {
        $product = $this->productInCategory($this->category(20.0));
        $product->setDiscountPercent(15.0);
        $product->setDiscountDisabled(true);

        $this->assertSame(0.0, $this->subject->getDiscountPercent($product, 'maxAcrossTree'));
        $this->assertSame(0.0, $this->subject->getDiscountPercent($product, 'nearestCategory'));
    }

    #[Test]
    public function maxAcrossTreePicksTheHighestDiscountAnywhereInTheAncestorChain(): void
    {
        $root = $this->category(10.0);
        $leaf = $this->category(5.0);
        $leaf->setParentCategory($root);
        $product = $this->productInCategory($leaf);

        $this->assertSame(10.0, $this->subject->getDiscountPercent($product, 'maxAcrossTree'));
    }

    #[Test]
    public function maxAcrossTreeIgnoresACategorysOwnDiscountDisabledFlag(): void
    {
        $root = $this->category(10.0);
        $root->setDiscountDisabled(true);
        $leaf = $this->category(0.0);
        $leaf->setParentCategory($root);
        $product = $this->productInCategory($leaf);

        $this->assertSame(10.0, $this->subject->getDiscountPercent($product, 'maxAcrossTree'));
    }

    #[Test]
    public function nearestCategoryPrefersTheNearestNonZeroDiscountOverAHigherOneFurtherUp(): void
    {
        $root = $this->category(20.0);
        $leaf = $this->category(5.0);
        $leaf->setParentCategory($root);
        $product = $this->productInCategory($leaf);

        $this->assertSame(5.0, $this->subject->getDiscountPercent($product, 'nearestCategory'));
    }

    #[Test]
    public function nearestCategoryFallsThroughToTheParentWhenTheLeafHasNoDiscount(): void
    {
        $root = $this->category(20.0);
        $leaf = $this->category(0.0);
        $leaf->setParentCategory($root);
        $product = $this->productInCategory($leaf);

        $this->assertSame(20.0, $this->subject->getDiscountPercent($product, 'nearestCategory'));
    }

    #[Test]
    public function nearestCategoryDisabledOnAnIntermediateCategoryBlocksTheWholeCascade(): void
    {
        $root = $this->category(20.0);
        $leaf = $this->category(0.0);
        $leaf->setDiscountDisabled(true);
        $leaf->setParentCategory($root);
        $product = $this->productInCategory($leaf);

        $this->assertSame(0.0, $this->subject->getDiscountPercent($product, 'nearestCategory'));
    }

    private function category(float $discountPercent): Category
    {
        $category = new Category();
        $category->setDiscountPercent($discountPercent);
        return $category;
    }

    private function productInCategory(Category $category): Product
    {
        $product = new Product();
        /** @var ObjectStorage<Category> $categories */
        $categories = new ObjectStorage();
        $categories->attach($category);
        $product->setCategories($categories);
        return $product;
    }
}
