<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EndToEnd;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * End-to-end coverage of the M3 checkout flow (per the M3 plan's verification section): a
 * related-product upsell visible on the basket, a combinable voucher replaced by a non-combinable
 * one, a partial credit-points spend, and a guest checkout that is rejected for the points step but
 * still completes for the voucher.
 */
final class M3CheckoutFlowTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private OrderPlacementService $orderPlacementService;
    private BasketService $basketService;
    private Product $mainProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/m3_end_to_end.csv');
        // CategoryDiscountPriceProvider still reads Extbase settings eagerly in its constructor,
        // which requires a request resolvable via $GLOBALS['TYPO3_REQUEST'] outside a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $this->basketService = $this->get(BasketService::class);
        $this->orderPlacementService = $this->get(OrderPlacementService::class);

        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        $this->mainProduct = $product;
    }

    #[Test]
    public function identifiedCustomerReplacesVoucherAndSpendsPartialPoints(): void
    {
        $request = $this->requestFor(7);
        $this->basketService->add($request, $this->mainProduct->getUid() ?? 0, null, 1);

        $relatedTitles = array_map(
            static fn(Product $related): string => $related->getTitle(),
            $this->mainProduct->getRelatedProducts()->toArray()
        );
        $this->assertSame(['Related Product'], $relatedTitles);

        $this->applyVoucher($request, 'COMBO1');
        $this->assertSame(['COMBO1'], $this->basketService->getAppliedVoucherCodes($request));

        $this->applyVoucher($request, 'SOLO');
        $this->assertSame(['SOLO'], $this->basketService->getAppliedVoucherCodes($request), 'A non-combinable voucher must replace, not join, an existing code.');

        $order = $this->orderPlacementService->place($request, $this->address(), 'invoice', new CheckoutChoices(30))->getOrder();

        $this->assertSame(9200, $order->getTotalGross()->getCents());
        $this->assertSame(800, $order->getDiscountTotal()->getCents());
        $this->assertSame(['SOLO'], $order->getVoucherCodes());

        $voucher = $this->get(VoucherRepository::class)->findOneByCode('SOLO');
        $this->assertNotNull($voucher);
        $this->assertSame(1, $this->get(VoucherRedemptionRepository::class)->countFor($voucher));

        $ledgerRows = $this->ledgerRows($order->getUid() ?? 0);
        $this->assertCount(2, $ledgerRows);
        $this->assertContainsEquals(['frontend_user' => 7, 'points' => 10, 'type' => 'earn'], $ledgerRows);
        $this->assertContainsEquals(['frontend_user' => 7, 'points' => -30, 'type' => 'redeem'], $ledgerRows);

        $this->assertSame([], $this->basketService->getAppliedVoucherCodes($request), 'A finalized order clears the basket, including its voucher codes.');
    }

    #[Test]
    public function guestCheckoutIsRejectedForPointsButStillCompletesForTheVoucher(): void
    {
        $request = $this->requestFor(0);
        $this->basketService->add($request, $this->mainProduct->getUid() ?? 0, null, 1);
        $this->applyVoucher($request, 'COMBO1');

        $orderCountBefore = $this->countOrders();
        try {
            $this->orderPlacementService->place($request, $this->address(), 'invoice', new CheckoutChoices(10));
            $this->fail('Expected InsufficientCreditPointsException was not thrown for a guest requesting points.');
        } catch (InsufficientCreditPointsException) {
            // expected: guests always have a zero balance
        }
        $this->assertSame($orderCountBefore, $this->countOrders(), 'A rejected points request must not place an order.');

        $order = $this->orderPlacementService->place($request, $this->address(), 'invoice')->getOrder();

        $this->assertSame(9000, $order->getTotalGross()->getCents());
        $this->assertSame(1000, $order->getDiscountTotal()->getCents());
        $this->assertSame(['COMBO1'], $order->getVoucherCodes());
        $this->assertSame([], $this->ledgerRows($order->getUid() ?? 0), 'Guests never touch the credit points ledger.');
    }

    private function applyVoucher(ServerRequestInterface $request, string $code): void
    {
        $voucherService = $this->get(VoucherService::class);
        $frontendUser = $this->get(FrontendUserResolver::class)->getUid($request);
        $basketGoodsTotal = $this->basketService->getBasketViewModel($request)->getTotalGross();

        $newVoucher = $voucherService->resolve($code, $basketGoodsTotal, $frontendUser);
        $existingCodes = $this->basketService->getAppliedVoucherCodes($request);
        $existingVouchers = $voucherService->buildDiscountSummary($existingCodes, $basketGoodsTotal, $frontendUser)->getAppliedVouchers();
        if (!$voucherService->canCoexist($existingVouchers, $newVoucher)) {
            $this->basketService->clearVoucherCodes($request);
        }
        $this->basketService->addVoucherCode($request, $newVoucher->getCode());
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $frontendUser->user = ['uid' => $frontendUserUid];
        }
        $site = new Site('products', 1, ['settings' => ['products' => [
            'creditPoints' => ['enabled' => true],
        ]]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
    }

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ledgerRows(int $orderUid): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_creditpointstransaction')
            ->select(['frontend_user', 'points', 'type'], 'tx_products_domain_model_creditpointstransaction', ['order_uid' => $orderUid])
            ->fetchAllAssociative();
    }

    private function countOrders(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_order')
            ->executeQuery('SELECT COUNT(*) FROM tx_products_domain_model_order')
            ->fetchOne();
    }
}
