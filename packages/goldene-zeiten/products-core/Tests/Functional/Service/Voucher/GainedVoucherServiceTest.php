<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Voucher;

use GoldeneZeiten\Products\Core\Configuration\GainedVoucherConfiguration;
use GoldeneZeiten\Products\Core\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Voucher\GainedVoucherService;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class GainedVoucherServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    #[Test]
    #[DataProvider('nothingIssuedProvider')]
    public function nothingIsIssuedInVariousScenarios(bool $enabled, string $totalGross, string $minimumOrderValue): void
    {
        $voucher = $this->subject()->maybeIssue(
            $this->order(frontendUser: 5, totalGross: $totalGross),
            $this->configuration(enabled: $enabled, minimumOrderValue: $minimumOrderValue)
        );

        $this->assertNull($voucher);
    }

    public static function nothingIssuedProvider(): \Generator
    {
        yield 'feature is disabled' => ['enabled' => false, 'totalGross' => '100.00', 'minimumOrderValue' => '0.00'];
        yield 'below the minimum order value' => ['enabled' => true, 'totalGross' => '49.99', 'minimumOrderValue' => '50.00'];
    }

    #[Test]
    public function aQualifyingOrderIssuesANonCombinableSingleUseVoucher(): void
    {
        $order = $this->order(frontendUser: 5, totalGross: '100.00', orderNumber: 'ORD-42');
        $configuration = $this->configuration(enabled: true, minimumOrderValue: '50.00', rewardType: 'fixed', rewardValue: '7.50');
        $voucher = $this->subject()->maybeIssue($order, $configuration);

        $this->assertNotNull($voucher);
        $this->assertStringStartsWith('GAINED-', $voucher->getCode());
        $this->assertSame(VoucherDiscountType::FIXED, $voucher->getDiscountType());
        $this->assertSame('7.50', $voucher->getDiscountValue());
        $this->assertFalse($voucher->isCombinable());
        $this->assertSame(1, $voucher->getUsageLimit());
        $this->assertSame(5, $voucher->getBoundFrontendUser());
        $this->assertSame($order->getUid() ?? 0, $voucher->getGeneratedFromOrder());
    }

    #[Test]
    public function aGuestOrderIssuesAnUnboundVoucher(): void
    {
        $order = $this->order(frontendUser: 0, totalGross: '100.00');
        $voucher = $this->subject()->maybeIssue($order, $this->configuration(enabled: true, minimumOrderValue: '50.00'));

        $this->assertNotNull($voucher);
        $this->assertSame(0, $voucher->getBoundFrontendUser());
    }

    #[Test]
    public function issuedVouchersArePersistedAndFindableByCode(): void
    {
        $order = $this->order(frontendUser: 5, totalGross: '100.00');
        $voucher = $this->subject()->maybeIssue($order, $this->configuration(enabled: true, minimumOrderValue: '50.00'));
        $this->assertNotNull($voucher);

        $found = $this->get(VoucherRepository::class)->findOneByCode($voucher->getCode());
        $this->assertNotNull($found);
        $this->assertSame($voucher->getCode(), $found->getCode());
    }

    #[Test]
    public function twoIssuedVouchersGetDifferentCodes(): void
    {
        $configuration = $this->configuration(enabled: true, minimumOrderValue: '50.00');
        $subject = $this->subject();
        $first = $subject->maybeIssue($this->order(frontendUser: 5, totalGross: '100.00'), $configuration);
        $second = $subject->maybeIssue($this->order(frontendUser: 6, totalGross: '100.00'), $configuration);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->getCode(), $second->getCode());
    }

    private function subject(): GainedVoucherService
    {
        return $this->get(GainedVoucherService::class);
    }

    private function configuration(
        bool $enabled,
        string $minimumOrderValue = '0.00',
        string $rewardType = 'fixed',
        string $rewardValue = '5.00'
    ): GainedVoucherConfiguration {
        return new GainedVoucherConfiguration(
            $enabled,
            Money::fromDecimalString($minimumOrderValue),
            VoucherDiscountType::tryFrom($rewardType) ?? VoucherDiscountType::FIXED,
            $rewardValue
        );
    }

    private function order(int $frontendUser, string $totalGross, string $orderNumber = 'ORD-1'): Order
    {
        $order = new Order();
        $order->setOrderNumber($orderNumber);
        $order->setFrontendUser($frontendUser);
        $order->setTotalGross(Money::fromDecimalString($totalGross));
        $this->get(OrderRepository::class)->add($order);
        $this->get(PersistenceManagerInterface::class)->persistAll();
        return $order;
    }
}
