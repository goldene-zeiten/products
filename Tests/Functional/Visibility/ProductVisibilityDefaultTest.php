<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Visibility;

use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Visibility\ProductVisibilityResolver;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Test ProductVisibilityResolver with zero visibility checkers (default behavior).
 * No visibility checkers are registered, so all products should be visible.
 */
final class ProductVisibilityDefaultTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/frontend-test',
    ];

    #[Test]
    public function withNoCheckerRegisteredEveryProductIsVisible(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVisibilityDefaultTest/products.csv');
        $request = $this->anonymousSessionRequest();
        $resolver = $this->get(ProductVisibilityResolver::class);
        $productRepository = $this->get(ProductRepository::class);

        $product = $productRepository->findByUid(1);
        $this->assertInstanceOf(\GoldeneZeiten\Products\Domain\Model\Product::class, $product);

        $this->assertTrue($resolver->isVisible($product, $request));
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
