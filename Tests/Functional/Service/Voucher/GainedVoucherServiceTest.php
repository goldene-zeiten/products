<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Voucher;

use GoldeneZeiten\Products\Configuration\GainedVoucherConfiguration;
use GoldeneZeiten\Products\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Voucher\GainedVoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class GainedVoucherServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function nothingIsIssuedWhileTheFeatureIsDisabled(): void
    {
        $voucher = $this->subject()->maybeIssue($this->order(frontendUser: 5, totalGross: '100.00'), $this->configuration(enabled: false));

        self::assertNull($voucher);
    }

    #[Test]
    public function nothingIsIssuedBelowTheMinimumOrderValue(): void
    {
        $voucher = $this->subject()->maybeIssue($this->order(frontendUser: 5, totalGross: '49.99'), $this->configuration(enabled: true, minimumOrderValue: '50.00'));

        self::assertNull($voucher);
    }

    #[Test]
    public function aQualifyingOrderIssuesANonCombinableSingleUseVoucher(): void
    {
        $order = $this->order(frontendUser: 5, totalGross: '100.00', orderNumber: 'ORD-42');
        $configuration = $this->configuration(enabled: true, minimumOrderValue: '50.00', rewardType: 'fixed', rewardValue: '7.50');
        $voucher = $this->subject()->maybeIssue($order, $configuration);

        self::assertNotNull($voucher);
        self::assertStringStartsWith('GAINED-', $voucher->getCode());
        self::assertSame(VoucherDiscountType::FIXED, $voucher->getDiscountType());
        self::assertSame('7.50', $voucher->getDiscountValue());
        self::assertFalse($voucher->isCombinable());
        self::assertSame(1, $voucher->getUsageLimit());
        self::assertSame(5, $voucher->getBoundFrontendUser());
        self::assertSame($order->getUid() ?? 0, $voucher->getGeneratedFromOrder());
    }

    #[Test]
    public function aGuestOrderIssuesAnUnboundVoucher(): void
    {
        $order = $this->order(frontendUser: 0, totalGross: '100.00');
        $voucher = $this->subject()->maybeIssue($order, $this->configuration(enabled: true, minimumOrderValue: '50.00'));

        self::assertNotNull($voucher);
        self::assertSame(0, $voucher->getBoundFrontendUser());
    }

    #[Test]
    public function issuedVouchersArePersistedAndFindableByCode(): void
    {
        $order = $this->order(frontendUser: 5, totalGross: '100.00');
        $voucher = $this->subject()->maybeIssue($order, $this->configuration(enabled: true, minimumOrderValue: '50.00'));
        self::assertNotNull($voucher);

        $found = $this->get(VoucherRepository::class)->findOneByCode($voucher->getCode());
        self::assertNotNull($found);
        self::assertSame($voucher->getCode(), $found->getCode());
    }

    #[Test]
    public function twoIssuedVouchersGetDifferentCodes(): void
    {
        $configuration = $this->configuration(enabled: true, minimumOrderValue: '50.00');
        $subject = $this->subject();
        $first = $subject->maybeIssue($this->order(frontendUser: 5, totalGross: '100.00'), $configuration);
        $second = $subject->maybeIssue($this->order(frontendUser: 6, totalGross: '100.00'), $configuration);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first->getCode(), $second->getCode());
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
