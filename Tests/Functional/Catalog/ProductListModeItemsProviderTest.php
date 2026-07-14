<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Catalog;

use GoldeneZeiten\Products\Core\Backend\Form\ProductListModeItemsProvider;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProductListModeItemsProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
        'goldene-zeiten/products-listmode-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
    }

    #[Test]
    public function populateAppendsRegisteredModesToExistingItems(): void
    {
        $provider = $this->get(ProductListModeItemsProvider::class);
        $parameters = ['items' => [['label' => 'All', 'value' => 'all']]];

        $provider->populate($parameters);

        $values = array_column($parameters['items'], 'value');
        // Pre-existing item should still be there
        $this->assertContains('all', $values);
        // Registered modes should be appended
        $this->assertContains('fixture-featured', $values);
        $this->assertContains('affordable', $values);
    }
}
