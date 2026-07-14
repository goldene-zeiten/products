<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\DataProcessing;

use GoldeneZeiten\Products\Core\DataProcessing\CategoryProductTreeProcessor;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class CategoryProductTreeProcessorTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryProductTreeProcessorTest/category_tree.csv');
    }

    #[Test]
    public function withoutAnEntryPointCategoryTheProcessedDataStaysUnchanged(): void
    {
        $subject = $this->get(CategoryProductTreeProcessor::class);

        $processedData = $subject->process($this->get(ContentObjectRenderer::class), [], [], ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $processedData);
    }

    #[Test]
    public function withANonExistentEntryPointCategoryTheProcessedDataStaysUnchanged(): void
    {
        $subject = $this->get(CategoryProductTreeProcessor::class);

        $processedData = $subject->process(
            $this->get(ContentObjectRenderer::class),
            [],
            ['entryPointCategory' => 999999],
            ['foo' => 'bar']
        );

        $this->assertSame(['foo' => 'bar'], $processedData);
    }

    #[Test]
    public function defaultConfigurationBuildsTheEntryPointCategoryWithOneLevelOfChildrenAndItsProducts(): void
    {
        $subject = $this->get(CategoryProductTreeProcessor::class);

        $processedData = $subject->process(
            $this->get(ContentObjectRenderer::class),
            [],
            ['entryPointCategory' => 6],
            []
        );

        $tree = $processedData['categoryTree'];
        $this->assertSame(6, $tree['uid']);
        $this->assertSame('Sub Category 5', $tree['title']);
        $this->assertCount(3, $tree['children']);
        $lastCategoryThree = $this->findChildByUid($tree['children'], 22);
        $this->assertCount(2, $lastCategoryThree['products']);
        $this->assertSame('Product 1', $lastCategoryThree['products'][0]['title']);
        $this->assertSame([], $lastCategoryThree['children']);
    }

    #[Test]
    public function theAsConfigurationControlsTheTargetVariableName(): void
    {
        $subject = $this->get(CategoryProductTreeProcessor::class);

        $processedData = $subject->process(
            $this->get(ContentObjectRenderer::class),
            [],
            ['entryPointCategory' => 6, 'as' => 'myTree'],
            []
        );

        $this->assertArrayHasKey('myTree', $processedData);
        $this->assertArrayNotHasKey('categoryTree', $processedData);
    }

    #[Test]
    public function levelsControlsHowManyLevelsBeneathTheEntryPointAreBuilt(): void
    {
        $subject = $this->get(CategoryProductTreeProcessor::class);

        $processedData = $subject->process(
            $this->get(ContentObjectRenderer::class),
            [],
            ['entryPointCategory' => 1, 'levels' => 2],
            []
        );

        $subCategoryFive = $this->findChildByUid($processedData['categoryTree']['children'], 6);
        $this->assertCount(3, $subCategoryFive['children']);
    }

    #[Test]
    public function minItemsSuppressesChildrenBelowTheConfiguredThreshold(): void
    {
        $subject = $this->get(CategoryProductTreeProcessor::class);

        $processedData = $subject->process(
            $this->get(ContentObjectRenderer::class),
            [],
            ['entryPointCategory' => 6, 'minItems' => 4],
            []
        );

        $this->assertSame([], $processedData['categoryTree']['children']);
    }

    #[Test]
    public function maxItemsTruncatesTheDirectChildrenOfTheEntryPoint(): void
    {
        $subject = $this->get(CategoryProductTreeProcessor::class);

        $processedData = $subject->process(
            $this->get(ContentObjectRenderer::class),
            [],
            ['entryPointCategory' => 6, 'maxItems' => 2],
            []
        );

        $this->assertCount(2, $processedData['categoryTree']['children']);
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function findChildByUid(array $children, int $uid): array
    {
        foreach ($children as $child) {
            if ($child['uid'] === $uid) {
                return $child;
            }
        }
        $this->fail(sprintf('No child with uid %d found.', $uid));
    }
}
