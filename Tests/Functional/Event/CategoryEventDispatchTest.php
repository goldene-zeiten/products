<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Event;

use GoldeneZeiten\Products\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\EventFixture\ModifyCategoryQueryListener;
use GoldeneZeiten\Products\EventFixture\ModifyCategoryTreeListener;
use GoldeneZeiten\Products\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CategoryEventDispatchTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryEventDispatchTest/category_tree.csv');
        ModifyCategoryTreeListener::$enabled = false;
        ModifyCategoryTreeListener::$invocationCount = 0;
        ModifyCategoryQueryListener::$enabled = false;
        ModifyCategoryQueryListener::$invocationCount = 0;
    }

    #[Test]
    public function modifyCategoryTreeEventIsDispatchedAndMutationTakesEffect(): void
    {
        ModifyCategoryTreeListener::$enabled = true;
        ModifyCategoryTreeListener::$invocationCount = 0;

        $subject = $this->get(CategoryTreeService::class);
        $tree = $subject->getTree();

        $this->assertGreaterThanOrEqual(1, ModifyCategoryTreeListener::$invocationCount);
        $this->assertCount(1, $tree);
        $this->assertSame('Main Category 2', $tree[0]->getCategory()->getTitle());
    }

    #[Test]
    public function modifyCategoryQueryEventIsDispatchedAndMutationTakesEffect(): void
    {
        ModifyCategoryQueryListener::$enabled = true;
        ModifyCategoryQueryListener::$invocationCount = 0;

        $subject = $this->get(CategoryRepository::class);
        $categories = $subject->findAllIgnoringStoragePage();

        $this->assertGreaterThanOrEqual(1, ModifyCategoryQueryListener::$invocationCount);
        $this->assertCount(1, $categories);
        $this->assertSame(1, $categories[0]->getUid());
    }
}
