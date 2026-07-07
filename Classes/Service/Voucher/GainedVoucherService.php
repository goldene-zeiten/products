<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Voucher;

use GoldeneZeiten\Products\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Repository\GainedVoucherRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\VoucherGeneratedEvent;
use GoldeneZeiten\Products\Service\Voucher\Exception\GainedVoucherCodeGenerationFailedException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Auto-issues a one-time reward voucher for any order that clears a configurable minimum value -
 * decoupled from gift orders (unlike legacy, which only rewarded placing a gift).
 */
final class GainedVoucherService
{
    private const MAX_CODE_ATTEMPTS = 5;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly GainedVoucherRepository $gainedVoucherRepository,
        private readonly VoucherRepository $voucherRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function isEnabled(): bool
    {
        return (bool)($this->settings['vouchers']['gained']['enabled'] ?? false);
    }

    /**
     * No-op (returns null) while disabled or below the threshold - a disabled feature must never
     * generate a code, same reasoning already applied to credit points.
     */
    public function maybeIssue(Order $order): ?Voucher
    {
        if (!$this->isEnabled() || !$this->meetsMinimumOrderValue($order)) {
            return null;
        }
        $voucher = $this->buildVoucher($order);
        $this->gainedVoucherRepository->add($voucher);
        $this->persistenceManager->persistAll();
        $this->eventDispatcher->dispatch(new VoucherGeneratedEvent($voucher, $order));
        return $voucher;
    }

    private function meetsMinimumOrderValue(Order $order): bool
    {
        return $order->getTotalGross()->getCents() >= $this->minimumOrderValue()->getCents();
    }

    private function minimumOrderValue(): Money
    {
        return Money::fromDecimalString((string)($this->settings['vouchers']['gained']['minimumOrderValue'] ?? '0.00'));
    }

    /**
     * Non-combinable and single-use, since a reward is meant to be one discrete perk, not a
     * stackable discount. Bound to the ordering customer when known; guest orders (frontend_user
     * 0) leave it unbound, matching Voucher::isAvailableToFrontendUser()'s "0 = anyone" semantics.
     */
    private function buildVoucher(Order $order): Voucher
    {
        $voucher = new Voucher();
        $voucher->setCode($this->generateUniqueCode());
        $voucher->setTitle(sprintf('Reward for order %s', $order->getOrderNumber()));
        $voucher->setDiscountType($this->rewardType());
        $voucher->setDiscountValue($this->rewardValue());
        $voucher->setCombinable(false);
        $voucher->setUsageLimit(1);
        $voucher->setBoundFrontendUser($order->getFrontendUser());
        $voucher->setGeneratedFromOrder($order->getUid() ?? 0);
        return $voucher;
    }

    private function rewardType(): VoucherDiscountType
    {
        $type = (string)($this->settings['vouchers']['gained']['rewardType'] ?? 'fixed');
        return VoucherDiscountType::tryFrom($type) ?? VoucherDiscountType::FIXED;
    }

    private function rewardValue(): string
    {
        return (string)($this->settings['vouchers']['gained']['rewardValue'] ?? '5.00');
    }

    /**
     * @throws GainedVoucherCodeGenerationFailedException
     */
    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < self::MAX_CODE_ATTEMPTS; $attempt++) {
            $code = 'GAINED-' . strtoupper(bin2hex(random_bytes(4)));
            if ($this->voucherRepository->findOneByCode($code) === null) {
                return $code;
            }
        }
        throw new GainedVoucherCodeGenerationFailedException(
            sprintf('Could not generate a unique gained-voucher code after %d attempts.', self::MAX_CODE_ATTEMPTS),
            1783700000
        );
    }
}
