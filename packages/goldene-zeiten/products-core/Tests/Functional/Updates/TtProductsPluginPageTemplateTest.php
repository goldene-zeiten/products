<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * tt_products lists were often configured per page in TypoScript: which storage folders they list
 * (`plugin.tt_products.pid_list`), how many products a page shows and in which order
 * (`conf.tt_products.ALL.limit` / `orderBy`). The template of the element's page, merged down the rootline,
 * carries them into the migrated list.
 */
final class TtProductsPluginPageTemplateTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginPageTemplateTest/page_templates.csv');
    }

    #[Test]
    #[DataProvider('elementProvider')]
    public function theListTakesTheSettingsOfItsPageTemplate(int $uid, string $categories, int $itemsPerPage, string $sorting): void
    {
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $row = $this->contentRow($uid);
        $this->assertSame('productscore_productlist', $row['CType']);
        $this->assertSame($categories, (string)$row['tx_products_category']);
        $this->assertSame($itemsPerPage, (int)$row['tx_products_items_per_page']);
        $this->assertSame($sorting, (string)$row['tx_products_sorting']);
    }

    public static function elementProvider(): \Generator
    {
        yield 'pid_list, limit and price order of the page' => [
            'uid' => 200,
            'categories' => '7',
            'itemsPerPage' => 36,
            'sorting' => 'price_asc',
        ];
        yield 'limit of the root template, uid order as the default order' => [
            'uid' => 201,
            'categories' => '',
            'itemsPerPage' => 144,
            'sorting' => 'manual',
        ];
        yield 'the element starting point wins over pid_list' => [
            'uid' => 202,
            'categories' => '3',
            'itemsPerPage' => 36,
            'sorting' => 'price_asc',
        ];
    }

    #[Test]
    public function aPidListFolderWithoutCategoryIsReported(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringContainsString('tt_content uid 203 on page 11 ("Unikate") was migrated without these legacy settings: pid_list = "369"', $output->fetch());
    }

    /**
     * @return array<string, mixed>
     */
    private function contentRow(int $uid): array
    {
        $row = $this->getConnectionPool()->getConnectionForTable('tt_content')->select(['*'], 'tt_content', ['uid' => $uid])->fetchAssociative();
        $this->assertIsArray($row);
        return $row;
    }
}
