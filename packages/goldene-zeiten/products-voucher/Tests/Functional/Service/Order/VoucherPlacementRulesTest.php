<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Voucher\Discount\VoucherCheckoutState;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherRedemptionFailedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * The voucher rules the basket enforces when a code is entered hold again when the order is placed, and
 * the redemption records add up to the discount the order actually got.
 */
final class VoucherPlacementRulesTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-voucher',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/VoucherPlacementRulesTest/voucher_rules.csv');
    }

    #[Test]
    #[DataProvider('alreadyDiscountedDataProvider')]
    public function aNonCombinableVoucherIsRefusedOnAnAlreadyDiscountedBasket(int $productUid, float $customerDiscountPercent): void
    {
        $this->expectException(VoucherRedemptionFailedException::class);
        $this->expectExceptionCode(1783426407);

        $this->place($productUid, $customerDiscountPercent, ['SOLO']);
    }

    public static function alreadyDiscountedDataProvider(): \Generator
    {
        yield 'a customer discount' => [
            'productUid' => 1,
            'customerDiscountPercent' => 10.0,
        ];
        yield 'an active price period' => [
            'productUid' => 2,
            'customerDiscountPercent' => 0.0,
        ];
    }

    #[Test]
    public function aCombinableVoucherStillAppliesOnAnAlreadyDiscountedBasket(): void
    {
        $order = $this->place(1, 10.0, ['STACK']);

        $this->assertSame(900, $order->getDiscountTotal()->getCents());
    }

    #[Test]
    public function aNonCombinableVoucherAppliesOnAnUndiscountedBasket(): void
    {
        $order = $this->place(1, 0.0, ['SOLO']);

        $this->assertSame(1000, $order->getDiscountTotal()->getCents());
    }

    #[Test]
    public function redemptionsRecordEachVouchersShareOfTheCappedDiscount(): void
    {
        $order = $this->place(1, 0.0, ['BIG', 'MORE']);

        $this->assertSame(10000, $order->getDiscountTotal()->getCents());
        $this->assertSame([3 => 8000, 4 => 2000], $this->redeemedShares());
    }

    /**
     * @param string[] $voucherCodes
     */
    private function place(int $productUid, float $customerDiscountPercent, array $voucherCodes): Order
    {
        $request = $this->request($customerDiscountPercent);
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, $productUid, null, 1);
        foreach ($voucherCodes as $voucherCode) {
            $this->get(VoucherCheckoutState::class)->addCode($request, $voucherCode);
        }

        return $this->get(OrderCreationService::class)->create(
            $request,
            $basketService->getBasketViewModel($request, 'DE'),
            new CheckoutSelections(),
            new Address(email: 'buyer@example.com', country: 'DE'),
            $this->get(PaymentMethodRegistry::class)->get('invoice')
        );
    }

    private function request(float $customerDiscountPercent): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $frontendUser->user = [
            'uid' => 7,
            'usergroup' => '',
            'tx_products_discount_percent' => $customerDiscountPercent,
        ];
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', new Site('products', 1, ['settings' => ['products' => []]]))
            ->withAttribute('frontend.user', $frontendUser);
    }

    /**
     * @return array<int, int> discount cents by voucher uid
     */
    private function redeemedShares(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_voucherredemption');
        $result = $queryBuilder
            ->select('voucher_uid', 'discount_total')
            ->from('tx_products_domain_model_voucherredemption')
            ->where($queryBuilder->expr()->gt('order_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('voucher_uid')
            ->executeQuery();
        $shares = [];
        while ($row = $result->fetchAssociative()) {
            $shares[(int)$row['voucher_uid']] = (int)$row['discount_total'];
        }
        return $shares;
    }
}
