<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsArticleAttributeUpgradeWizard;
use GoldeneZeiten\Products\Core\Updates\TtProductsArticleUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * What tells the articles of a product apart in tt_products - a material, an alloy - lives in article columns;
 * the columns named in `migration.articleAttributeColumns` become attributes, so the article switch can offer
 * them.
 */
final class TtProductsArticleAttributeTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsArticleAttributeTest/articles.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['articleAttributeColumns'] = 'material:Material / Legierung, color9:Missing';
    }

    #[Test]
    public function eachConfiguredColumnBecomesAnAttributeTheArticlesCarryAValueOf(): void
    {
        $subject = $this->get(TtProductsArticleAttributeUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsArticleAttributeTest/Result/attributes.csv');
    }

    #[Test]
    public function runningTheWizardAgainCreatesNothingTwice(): void
    {
        $subject = $this->get(TtProductsArticleAttributeUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsArticleAttributeTest/Result/attributes.csv');
    }

    #[Test]
    public function aConfiguredColumnTheLegacyTableLacksIsReported(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsArticleAttributeUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringContainsString('tt_products_articles has no column "color9"; it is left out.', $output->fetch());
    }

    #[Test]
    public function theArticleMigrationNoLongerReportsAConfiguredColumn(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringNotContainsString('data in "material"', $output->fetch());
    }
}
