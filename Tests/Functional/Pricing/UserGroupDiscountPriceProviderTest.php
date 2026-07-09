<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Pricing;

use GoldeneZeiten\Products\Domain\Model\PriceTier;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Pricing\PriceProviderInterface;
use GoldeneZeiten\Products\Pricing\UserGroupDiscountPriceProvider;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class UserGroupDiscountPriceProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private UserGroupDiscountPriceProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/frontend_user_discounts.csv');
        $this->subject = $this->get(UserGroupDiscountPriceProvider::class);
    }

    #[Test]
    public function priceProviderInterfaceIsAliasedToTheUserGroupDiscountProvider(): void
    {
        self::assertInstanceOf(UserGroupDiscountPriceProvider::class, $this->get(PriceProviderInterface::class));
    }

    #[Test]
    public function withoutARequestTheUndiscountedPricePassesThrough(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));

        self::assertSame(10000, $this->subject->getUnitPrice($product, null, 1)->getCents());
    }

    #[Test]
    public function anAnonymousVisitorGetsTheUndiscountedPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));

        self::assertSame(10000, $this->subject->getUnitPrice($product, null, 1, $this->requestFor(0))->getCents());
    }

    #[Test]
    public function aDiscountedGroupsShopperGetsTheReducedPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));

        // user 2 belongs to group 1, which carries a 10% discount (see frontend_user_discounts.csv)
        self::assertSame(9000, $this->subject->getUnitPrice($product, null, 1, $this->requestFor(2))->getCents());
    }

    #[Test]
    public function theDiscountAppliesOnTopOfAGraduatedTierPrice(): void
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
        self::assertSame(4250, $this->subject->getUnitPrice($product, null, 10, $this->requestFor(3))->getCents());
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
