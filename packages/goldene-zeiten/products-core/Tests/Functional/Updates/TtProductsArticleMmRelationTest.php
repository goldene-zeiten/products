<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsArticleUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * tt_products installations running an article mode other than 0 link articles to their product
 * through `tt_products_products_mm_articles` and leave `tt_products_articles.uid_product` at 0.
 */
final class TtProductsArticleMmRelationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsArticleMmRelationTest/tt_products_articles_mm.csv');
    }

    #[Test]
    public function articlesLinkedThroughTheMmTableAreMigratedToTheirProduct(): void
    {
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsArticleMmRelationTest/Result/articles_linked_through_mm.csv');
    }

    #[Test]
    public function anArticleSharedByTwoProductsIsReportedForTheProductItWasNotAttachedTo(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringContainsString(
            'tt_products_articles uid 2 is also linked to tt_products uid 2',
            $output->fetch()
        );
    }

    #[Test]
    public function anArticleWhoseOnlyLinkIsDeletedIsSkippedAsOrphan(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertSame(
            0,
            $this->get(LegacyMigrationHelper::class)->resolveLocalUid('tt_products_articles', 3, 'tx_products_domain_model_article')
        );
        $this->assertStringContainsString('tt_products_articles uid 3 has no product', $output->fetch());
    }
}
