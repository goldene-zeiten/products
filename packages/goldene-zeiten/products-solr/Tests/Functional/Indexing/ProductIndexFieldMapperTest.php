<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Solr\Tests\Functional\Indexing;

use GoldeneZeiten\Products\Solr\Indexing\ProductIndexFieldMapper;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class ProductIndexFieldMapperTest extends AbstractFunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'reports',
        'scheduler',
        'tstemplate',
    ];

    protected array $testExtensionsToLoad = [
        'apache-solr-for-typo3/solr',
        'goldene-zeiten/products-solr',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/products.csv');
    }

    #[Test]
    public function categoryPathsBuildOneDepthPrefixedEntryPerAncestorLevel(): void
    {
        $result = $this->subjectFor(1)->categoryPaths('', []);

        $this->assertSame('0-/Books|1-/Books/Cooking', $result);
    }

    #[Test]
    public function attributeValuesTraverseArticlesToAttributeLabels(): void
    {
        $result = $this->subjectFor(1)->attributeValues('', []);

        $this->assertSame('Color: Red|Size: XL', $result);
    }

    #[Test]
    public function unknownProductYieldsEmptyValues(): void
    {
        $subject = $this->subjectFor(9999);

        $this->assertSame('', $subject->categoryPaths('', []));
        $this->assertSame('', $subject->attributeValues('', []));
    }

    private function subjectFor(int $productUid): ProductIndexFieldMapper
    {
        $subject = $this->get(ProductIndexFieldMapper::class);
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->data = ['uid' => $productUid];
        $subject->setContentObjectRenderer($contentObjectRenderer);

        return $subject;
    }
}
