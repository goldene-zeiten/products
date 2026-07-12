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
use GoldeneZeiten\Products\Tests\Functional\Fixtures\FixtureConfigurationManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
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
    }

    #[Test]
    public function priceProviderInterfaceIsAliasedToTheCategoryDiscountProvider(): void
    {
        $this->assertInstanceOf(CategoryDiscountPriceProvider::class, $this->get(PriceProviderInterface::class));
    }

    /**
     * @param array{minQuantity: int, price: string}|null $priceTier
     */
    #[Test]
    #[DataProvider('unitPriceDataProvider')]
    public function getUnitPriceAppliesTheExpectedDiscount(
        ?float $categoryDiscountPercent,
        ?array $priceTier,
        int $quantity,
        ?int $frontendUserUid,
        int $expectedCents
    ): void {
        $product = $categoryDiscountPercent !== null ? $this->productInCategory($categoryDiscountPercent) : new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));

        if ($priceTier !== null) {
            /** @var ObjectStorage<PriceTier> $tiers */
            $tiers = new ObjectStorage();
            $tier = new PriceTier();
            $tier->setMinQuantity($priceTier['minQuantity']);
            $tier->setPrice(Money::fromDecimalString($priceTier['price']));
            $tiers->attach($tier);
            $product->setPriceTiers($tiers);
        }

        $request = $frontendUserUid !== null ? $this->requestFor($frontendUserUid) : null;

        $this->assertSame($expectedCents, $this->subject('maxAcrossTree')->getUnitPrice($product, null, $quantity, $request)->getCents());
    }

    public static function unitPriceDataProvider(): \Generator
    {
        yield 'without a request the undiscounted price passes through' => [
            'categoryDiscountPercent' => null,
            'priceTier' => null,
            'quantity' => 1,
            'frontendUserUid' => null,
            'expectedCents' => 10000,
        ];
        yield 'an anonymous visitor gets the undiscounted price' => [
            'categoryDiscountPercent' => null,
            'priceTier' => null,
            'quantity' => 1,
            'frontendUserUid' => 0,
            'expectedCents' => 10000,
        ];
        // user 2 belongs to group 1, which carries a 10% discount (see frontend_user_discounts.csv)
        yield 'a discounted groups shopper gets the reduced price' => [
            'categoryDiscountPercent' => null,
            'priceTier' => null,
            'quantity' => 1,
            'frontendUserUid' => 2,
            'expectedCents' => 9000,
        ];
        // user 3 has a personal 15% discount (see frontend_user_discounts.csv)
        yield 'the user group discount applies on top of a graduated tier price' => [
            'categoryDiscountPercent' => null,
            'priceTier' => ['minQuantity' => 10, 'price' => '50.00'],
            'quantity' => 10,
            'frontendUserUid' => 3,
            'expectedCents' => 4250,
        ];
        yield 'a category discount applies without any frontend user' => [
            'categoryDiscountPercent' => 20.0,
            'priceTier' => null,
            'quantity' => 1,
            'frontendUserUid' => 0,
            'expectedCents' => 8000,
        ];
        // user 2's group discount (10%) is smaller than the category discount (30%) - not stacked
        yield 'the bigger category discount wins over a smaller user group discount' => [
            'categoryDiscountPercent' => 30.0,
            'priceTier' => null,
            'quantity' => 1,
            'frontendUserUid' => 2,
            'expectedCents' => 7000,
        ];
        // user 3's personal discount (15%) is bigger than the category discount (5%) - not stacked
        yield 'the bigger user group discount wins over a smaller category discount' => [
            'categoryDiscountPercent' => 5.0,
            'priceTier' => null,
            'quantity' => 1,
            'frontendUserUid' => 3,
            'expectedCents' => 8500,
        ];
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
        $this->assertSame(9500, $this->subject('nearestCategory')->getUnitPrice($product, null, 1, $this->requestFor(0))->getCents());
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
        return new FixtureConfigurationManager(['pricing' => ['discountFieldMode' => $discountFieldMode]]);
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
