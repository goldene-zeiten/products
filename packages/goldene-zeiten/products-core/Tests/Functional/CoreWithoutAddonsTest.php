<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional;

use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Guards that functionality split out into an add-on really left the core.
 *
 * Every other test here loads core alone and would fail if core *needed* an add-on. What that does not
 * catch is an add-on's registration creeping back *into* core - which is what this test is for. Extend it
 * with the content element and fields of each further add-on that gets extracted.
 */
final class CoreWithoutAddonsTest extends AbstractFunctionalTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function addonContentElements(): array
    {
        return [
            'search' => ['productssearch_search'],
            'recently viewed' => ['productsrecentlyviewed_recentlyviewed'],
            'wishlist' => ['productswishlist_wishlist'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function addonContentElementFields(): array
    {
        return [
            'search browse mode' => ['tx_products_search_browse_mode'],
            'search target' => ['tx_products_search_target'],
            'search field' => ['tx_products_search_field'],
            'recently viewed mode' => ['tx_products_recentlyviewed_mode'],
        ];
    }

    /**
     * Columns an add-on adds onto one of core's own tables. Core must not declare them itself - the add-on
     * owns both the column and every read of it.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function addonColumnsOnCoreTables(): array
    {
        return [
            'credit points on product' => ['tx_products_domain_model_product', 'credit_points'],
        ];
    }

    #[Test]
    #[DataProvider('addonContentElements')]
    public function anAddonsContentElementIsNotRegisteredByCore(string $contentElement): void
    {
        $this->assertArrayNotHasKey($contentElement, $GLOBALS['TCA']['tt_content']['types'] ?? []);
    }

    #[Test]
    #[DataProvider('addonContentElementFields')]
    public function anAddonsContentElementFieldIsNotRegisteredByCore(string $field): void
    {
        $this->assertArrayNotHasKey($field, $GLOBALS['TCA']['tt_content']['columns'] ?? []);
    }

    #[Test]
    #[DataProvider('addonColumnsOnCoreTables')]
    public function anAddonsColumnOnACoreTableIsNotRegisteredByCore(string $table, string $column): void
    {
        $this->assertArrayNotHasKey($column, $GLOBALS['TCA'][$table]['columns'] ?? []);
    }

    /**
     * The catalog queries the search add-on is built on are core's own API and stay here, so core keeps
     * answering them with the add-on uninstalled.
     */
    #[Test]
    public function theCatalogQueriesAnAddonBuildsOnStillAnswerWithoutIt(): void
    {
        $this->importCSVDataSet(self::sharedFixture('search.csv'));

        $repository = $this->get(ProductRepository::class);

        $this->assertGreaterThan(0, $repository->countSearchResults('Shoes', null));
    }
}
