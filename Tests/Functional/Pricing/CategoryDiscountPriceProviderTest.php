<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Pricing;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\PriceTier;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Pricing\CategoryDiscountPriceProvider;
use GoldeneZeiten\Products\Pricing\CategoryDiscountResolver;
use GoldeneZeiten\Products\Pricing\GraduatedPriceProvider;
use GoldeneZeiten\Products\Pricing\PriceProviderInterface;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class CategoryDiscountPriceProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/frontend_user_discounts.csv');
        // CategoryDiscountPriceProvider's fake ConfigurationManagerInterface (used by most tests
        // below) sidesteps this, but the DI-wired real one (used by the wiring test) reads Extbase
        // settings eagerly in its constructor, which requires a request resolvable via
        // $GLOBALS['TYPO3_REQUEST'] outside a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    public function priceProviderInterfaceIsAliasedToTheCategoryDiscountProvider(): void
    {
        self::assertInstanceOf(CategoryDiscountPriceProvider::class, $this->get(PriceProviderInterface::class));
    }

    #[Test]
    public function withoutARequestTheUndiscountedPricePassesThrough(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));

        self::assertSame(10000, $this->subject('maxAcrossTree')->getUnitPrice($product, null, 1)->getCents());
    }

    #[Test]
    public function anAnonymousVisitorGetsTheUndiscountedPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));

        self::assertSame(10000, $this->subject('maxAcrossTree')->getUnitPrice($product, null, 1, $this->requestFor(0))->getCents());
    }

    #[Test]
    public function aDiscountedGroupsShopperGetsTheReducedPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));

        // user 2 belongs to group 1, which carries a 10% discount (see frontend_user_discounts.csv)
        self::assertSame(9000, $this->subject('maxAcrossTree')->getUnitPrice($product, null, 1, $this->requestFor(2))->getCents());
    }

    #[Test]
    public function theUserGroupDiscountAppliesOnTopOfAGraduatedTierPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));
        /** @var ObjectStorage<PriceTier> $tiers */
        $tiers = new ObjectStorage();
        $tier = new PriceTier();
        $tier->setMinQuantity(10);
        $tier->setPrice(Money::fromDecimalString('50.00'));
        $tiers->attach($tier);
        $product->setPriceTiers($tiers);

        // user 3 has a personal 15% discount (see frontend_user_discounts.csv)
        self::assertSame(4250, $this->subject('maxAcrossTree')->getUnitPrice($product, null, 10, $this->requestFor(3))->getCents());
    }

    #[Test]
    public function aCategoryDiscountAppliesWithoutAnyFrontendUser(): void
    {
        $product = $this->productInCategory(20.0);
        $product->setPrice(Money::fromDecimalString('100.00'));

        self::assertSame(8000, $this->subject('maxAcrossTree')->getUnitPrice($product, null, 1, $this->requestFor(0))->getCents());
    }

    #[Test]
    public function theBiggerCategoryDiscountWinsOverASmallerUserGroupDiscount(): void
    {
        $product = $this->productInCategory(30.0);
        $product->setPrice(Money::fromDecimalString('100.00'));

        // user 2's group discount (10%) is smaller than the category discount (30%) - not stacked
        self::assertSame(7000, $this->subject('maxAcrossTree')->getUnitPrice($product, null, 1, $this->requestFor(2))->getCents());
    }

    #[Test]
    public function theBiggerUserGroupDiscountWinsOverASmallerCategoryDiscount(): void
    {
        $product = $this->productInCategory(5.0);
        $product->setPrice(Money::fromDecimalString('100.00'));

        // user 3's personal discount (15%) is bigger than the category discount (5%) - not stacked
        self::assertSame(8500, $this->subject('maxAcrossTree')->getUnitPrice($product, null, 1, $this->requestFor(3))->getCents());
    }

    #[Test]
    public function nearestCategoryModeIsHonouredWhenConfigured(): void
    {
        $root = new Category();
        $root->setDiscountPercent(30.0);
        $leaf = new Category();
        $leaf->setDiscountPercent(5.0);
        $leaf->setParentCategory($root);
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));
        /** @var ObjectStorage<Category> $categories */
        $categories = new ObjectStorage();
        $categories->attach($leaf);
        $product->setCategories($categories);

        // nearestCategory mode: the leaf's own 5% wins over the root's 30%, unlike maxAcrossTree
        self::assertSame(9500, $this->subject('nearestCategory')->getUnitPrice($product, null, 1, $this->requestFor(0))->getCents());
    }

    private function productInCategory(float $categoryDiscountPercent): Product
    {
        $category = new Category();
        $category->setDiscountPercent($categoryDiscountPercent);
        $product = new Product();
        /** @var ObjectStorage<Category> $categories */
        $categories = new ObjectStorage();
        $categories->attach($category);
        $product->setCategories($categories);
        return $product;
    }

    private function subject(string $discountFieldMode): CategoryDiscountPriceProvider
    {
        return new CategoryDiscountPriceProvider(
            $this->get(GraduatedPriceProvider::class),
            $this->get(FrontendUserResolver::class),
            $this->get(CategoryDiscountResolver::class),
            $this->fakeConfigurationManager($discountFieldMode)
        );
    }

    private function fakeConfigurationManager(string $discountFieldMode): ConfigurationManagerInterface
    {
        return new class ($discountFieldMode) implements ConfigurationManagerInterface {
            public function __construct(private readonly string $discountFieldMode) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['pricing' => ['discountFieldMode' => $this->discountFieldMode]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
            $row = $queryBuilder->select('*')
                ->from('fe_users')
                ->where($queryBuilder->expr()->eq('uid', $frontendUserUid))
                ->executeQuery()
                ->fetchAssociative();
            $frontendUser->user = $row !== false ? $row : ['uid' => $frontendUserUid];
        }
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }
}
