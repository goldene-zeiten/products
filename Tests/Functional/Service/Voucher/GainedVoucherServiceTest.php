<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Voucher;

use GoldeneZeiten\Products\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\GainedVoucherRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Voucher\GainedVoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class GainedVoucherServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function nothingIsIssuedWhileTheFeatureIsDisabled(): void
    {
        $voucher = $this->subject(enabled: false)->maybeIssue($this->order(frontendUser: 5, totalGross: '100.00'));

        self::assertNull($voucher);
    }

    #[Test]
    public function nothingIsIssuedBelowTheMinimumOrderValue(): void
    {
        $voucher = $this->subject(enabled: true, minimumOrderValue: '50.00')->maybeIssue($this->order(frontendUser: 5, totalGross: '49.99'));

        self::assertNull($voucher);
    }

    #[Test]
    public function aQualifyingOrderIssuesANonCombinableSingleUseVoucher(): void
    {
        $order = $this->order(frontendUser: 5, totalGross: '100.00', orderNumber: 'ORD-42');
        $voucher = $this->subject(enabled: true, minimumOrderValue: '50.00', rewardType: 'fixed', rewardValue: '7.50')->maybeIssue($order);

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
        $voucher = $this->subject(enabled: true, minimumOrderValue: '50.00')->maybeIssue($order);

        self::assertNotNull($voucher);
        self::assertSame(0, $voucher->getBoundFrontendUser());
    }

    #[Test]
    public function issuedVouchersArePersistedAndFindableByCode(): void
    {
        $order = $this->order(frontendUser: 5, totalGross: '100.00');
        $voucher = $this->subject(enabled: true, minimumOrderValue: '50.00')->maybeIssue($order);
        self::assertNotNull($voucher);

        $found = $this->get(VoucherRepository::class)->findOneByCode($voucher->getCode());
        self::assertNotNull($found);
        self::assertSame($voucher->getCode(), $found->getCode());
    }

    #[Test]
    public function twoIssuedVouchersGetDifferentCodes(): void
    {
        $subject = $this->subject(enabled: true, minimumOrderValue: '50.00');
        $first = $subject->maybeIssue($this->order(frontendUser: 5, totalGross: '100.00'));
        $second = $subject->maybeIssue($this->order(frontendUser: 6, totalGross: '100.00'));

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first->getCode(), $second->getCode());
    }

    private function subject(
        bool $enabled,
        string $minimumOrderValue = '0.00',
        string $rewardType = 'fixed',
        string $rewardValue = '5.00'
    ): GainedVoucherService {
        return new GainedVoucherService(
            $this->get(GainedVoucherRepository::class),
            $this->get(VoucherRepository::class),
            $this->get(PersistenceManagerInterface::class),
            $this->get(EventDispatcherInterface::class),
            $this->fakeConfigurationManager($enabled, $minimumOrderValue, $rewardType, $rewardValue)
        );
    }

    private function fakeConfigurationManager(bool $enabled, string $minimumOrderValue, string $rewardType, string $rewardValue): ConfigurationManagerInterface
    {
        return new class ($enabled, $minimumOrderValue, $rewardType, $rewardValue) implements ConfigurationManagerInterface {
            public function __construct(
                private readonly bool $enabled,
                private readonly string $minimumOrderValue,
                private readonly string $rewardType,
                private readonly string $rewardValue
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['vouchers' => ['gained' => [
                    'enabled' => $this->enabled,
                    'minimumOrderValue' => $this->minimumOrderValue,
                    'rewardType' => $this->rewardType,
                    'rewardValue' => $this->rewardValue,
                ]]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
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
