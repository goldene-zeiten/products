<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Visibility;

use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\EventFixture\DenyingVisibilityChecker;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Visibility\ProductVisibilityResolver;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Test ProductVisibilityResolver with multiple visibility checkers.
 * Tests deny-wins-over-allow aggregation logic.
 */
final class ProductVisibilityResolverTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        DenyingVisibilityChecker::$enabled = false;
        DenyingVisibilityChecker::$deniedProductUid = 0;
    }

    #[Test]
    public function denyWinsOverAllow(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVisibilityResolverTest/products.csv');
        DenyingVisibilityChecker::$enabled = true;
        DenyingVisibilityChecker::$deniedProductUid = 1;

        $request = $this->anonymousSessionRequest();
        $resolver = $this->get(ProductVisibilityResolver::class);
        $productRepository = $this->get(ProductRepository::class);

        $product1 = $productRepository->findByUid(1);
        $product2 = $productRepository->findByUid(2);
        $this->assertInstanceOf(\GoldeneZeiten\Products\Domain\Model\Product::class, $product1);
        $this->assertInstanceOf(\GoldeneZeiten\Products\Domain\Model\Product::class, $product2);

        $this->assertFalse($resolver->isVisible($product1, $request));
        $this->assertTrue($resolver->isVisible($product2, $request));
    }

    #[Test]
    public function allowWhenNoCheckerDenies(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVisibilityResolverTest/products.csv');

        $request = $this->anonymousSessionRequest();
        $resolver = $this->get(ProductVisibilityResolver::class);
        $productRepository = $this->get(ProductRepository::class);

        $product1 = $productRepository->findByUid(1);
        $this->assertInstanceOf(\GoldeneZeiten\Products\Domain\Model\Product::class, $product1);

        $this->assertTrue($resolver->isVisible($product1, $request));
    }

    private function anonymousSessionRequest(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
