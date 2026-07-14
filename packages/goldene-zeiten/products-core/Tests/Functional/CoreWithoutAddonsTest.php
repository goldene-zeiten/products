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
