<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Pricing\GraduatedPriceProvider;
use GoldeneZeiten\Products\Core\Pricing\PricePeriodPriceProvider;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Exercises {@see PricePeriodPriceProvider} and its combination with quantity tiers in
 * {@see GraduatedPriceProvider} against real Extbase-persisted data, rather than hand-built
 * models in a Unit test - the scenarios genuinely depend on multiple related rows (parent,
 * price periods, price tiers, fe_group scope), which this repo's convention says belongs in a
 * Functional test with a CSV fixture.
 */
final class PricePeriodPriceProviderTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/frontend_user_discounts.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PricePeriodPriceProviderTest/price_periods.csv');
    }

    #[Test]
    public function fallsBackToBasePriceWhenNoPeriodsExist(): void
    {
        $product = $this->product(1);

        $this->assertNull($this->get(PricePeriodPriceProvider::class)->findActivePeriodPrice($product, null, null));
        $this->assertSame(1999, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, null, 1)->getCents());
    }

    #[Test]
    public function activePublicPeriodOverridesBasePrice(): void
    {
        $product = $this->product(2);

        $this->assertSame(999, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, null, 1, $this->guestRequest())->getCents());
    }

    #[Test]
    public function expiredAndFuturePeriodsAreIgnored(): void
    {
        $product = $this->product(3);

        $this->assertSame(1999, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, null, 1, $this->guestRequest())->getCents());
    }

    #[Test]
    public function resellerOnlyPeriodAppliesOnlyToMatchingFeGroupMember(): void
    {
        $product = $this->product(4);
        $provider = $this->get(GraduatedPriceProvider::class);

        $this->assertSame(1200, $provider->getUnitPrice($product, null, 1, $this->resellerRequest())->getCents());
        $this->assertSame(1999, $provider->getUnitPrice($product, null, 1, $this->guestRequest())->getCents());
    }

    #[Test]
    public function publicAndResellerPeriodsCombineByLowestPriceUnderDefaultPolicy(): void
    {
        $product = $this->product(5);
        $provider = $this->get(GraduatedPriceProvider::class);

        $this->assertSame(1500, $provider->getUnitPrice($product, null, 1, $this->guestRequest())->getCents());
        $this->assertSame(1000, $provider->getUnitPrice($product, null, 1, $this->resellerRequest('lowestWins'))->getCents());
    }

    #[Test]
    public function resellerFixedPolicyKeepsTheResellerPriceEvenWhenHigherThanThePublicDiscount(): void
    {
        $product = $this->product(6);
        $provider = $this->get(GraduatedPriceProvider::class);

        $this->assertSame(800, $provider->getUnitPrice($product, null, 1, $this->guestRequest())->getCents());
        $this->assertSame(1200, $provider->getUnitPrice($product, null, 1, $this->resellerRequest('resellerFixed'))->getCents());
    }

    #[Test]
    public function articleLevelPeriodTakesPrecedenceOverProductLevelPeriod(): void
    {
        $product = $this->product(7);
        $article = $this->article(20);
        $provider = $this->get(PricePeriodPriceProvider::class);

        $articlePrice = $provider->findActivePeriodPrice($product, $article, $this->guestRequest());
        $this->assertNotNull($articlePrice);
        $this->assertSame(600, $articlePrice->getCents());

        $productPrice = $provider->findActivePeriodPrice($product, null, $this->guestRequest());
        $this->assertNotNull($productPrice);
        $this->assertSame(1400, $productPrice->getCents());
    }

    #[Test]
    public function quantityTierWinsWhenItIsLowerThanTheActivePeriod(): void
    {
        $product = $this->product(8);
        $provider = $this->get(GraduatedPriceProvider::class);

        // below the tier's min_quantity: only the period applies
        $this->assertSame(1700, $provider->getUnitPrice($product, null, 1, $this->guestRequest())->getCents());
        // at/above the tier's min_quantity: tier (1500) is lower than the period (1700)
        $this->assertSame(1500, $provider->getUnitPrice($product, null, 10, $this->guestRequest())->getCents());
    }

    #[Test]
    public function periodWinsWhenItIsLowerThanTheMatchingQuantityTier(): void
    {
        $product = $this->product(9);
        $provider = $this->get(GraduatedPriceProvider::class);

        // tier (1800) is higher than the period (1200), so the period wins even at the tier quantity
        $this->assertSame(1200, $provider->getUnitPrice($product, null, 10, $this->guestRequest())->getCents());
    }

    #[Test]
    public function belowFirstTierUsesBasePrice(): void
    {
        $product = $this->product(40);

        $this->assertSame(1999, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, null, 1)->getCents());
    }

    #[Test]
    public function exactTierBoundaryUsesThatTier(): void
    {
        $product = $this->product(40);

        $this->assertSame(1500, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, null, 10)->getCents());
    }

    #[Test]
    public function aboveLastTierUsesHighestMatchingTier(): void
    {
        $product = $this->product(40);

        $this->assertSame(1200, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, null, 1000)->getCents());
    }

    #[Test]
    public function articleTiersTakePrecedenceOverProductTiers(): void
    {
        $product = $this->product(41);
        $article = $this->article(42);

        $this->assertSame(900, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, $article, 10)->getCents());
    }

    #[Test]
    public function productTiersApplyWhenArticleHasNone(): void
    {
        $product = $this->product(41);
        $article = $this->article(43);

        $this->assertSame(2000, $this->get(GraduatedPriceProvider::class)->getUnitPrice($product, $article, 10)->getCents());
    }

    private function product(int $uid): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function article(int $uid): Article
    {
        $article = $this->get(ArticleRepository::class)->findByUid($uid);
        $this->assertInstanceOf(Article::class, $article);
        return $article;
    }

    private function guestRequest(): ServerRequestInterface
    {
        return new ServerRequest('http://localhost/');
    }

    private function resellerRequest(?string $precedencePolicy = null): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
        $row = $queryBuilder->select('*')
            ->from('fe_users')
            ->where($queryBuilder->expr()->eq('uid', 2)) // "group-discount" user, member of fe_group 1
            ->executeQuery()
            ->fetchAssociative();
        $frontendUser->user = $row !== false ? $row : ['uid' => 2, 'usergroup' => '1'];

        $request = (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
        if ($precedencePolicy !== null) {
            $site = new Site('products', 1, ['settings' => ['products' => [
                'pricing' => ['resellerPeriodPrecedence' => $precedencePolicy],
            ]]]);
            $request = $request->withAttribute('site', $site);
        }
        return $request;
    }
}
