<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Controller\Backend\CategoryTreeController;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * A product linked into several root categories is found, and revealed, under the root the tree shows.
 */
final class CategoryTreeSearchRootTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryTreeSearchRootTest/two_roots.csv');
        $this->setUpBackendUser(1);
    }

    /**
     * @param array<string, string> $parameters
     * @param string[] $expectedAncestors
     */
    #[Test]
    #[DataProvider('searchProvider')]
    public function aProductIsFoundUnderTheRootTheTreeShows(array $parameters, array $expectedAncestors): void
    {
        $subject = $this->get(CategoryTreeController::class);

        $response = $subject->filterDataAction((new ServerRequest('http://localhost/'))->withQueryParams(['query' => 'IB No'] + $parameters));

        $matches = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($matches);
        $products = array_values(array_filter($matches, static fn(array $match): bool => $match['identifier'] === 'product-20'));
        $this->assertCount(1, $products);
        $this->assertSame($expectedAncestors, $products[0]['ancestors']);
    }

    public static function searchProvider(): \Generator
    {
        yield 'without a root under its first category' => [
            'parameters' => [],
            'expectedAncestors' => ['category-10', 'category-11'],
        ];
        yield 'under the chosen root' => [
            'parameters' => ['root' => 'category-13'],
            'expectedAncestors' => ['category-13', 'category-15'],
        ];
    }

    #[Test]
    public function theRootlineOfAProductLeadsThroughTheChosenRoot(): void
    {
        $subject = $this->get(CategoryTreeController::class);

        $response = $subject->fetchRootlineAction((new ServerRequest('http://localhost/'))->withQueryParams([
            'identifier' => 'product-20',
            'root' => 'category-13',
        ]));

        $this->assertSame(['category-13', 'category-15'], json_decode((string)$response->getBody(), true));
    }
}
