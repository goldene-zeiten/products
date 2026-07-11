<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Voucher;

use GoldeneZeiten\Products\Configuration\GainedVoucherConfiguration;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Repository\GainedVoucherRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Event\VoucherGeneratedEvent;
use GoldeneZeiten\Products\Service\Voucher\Exception\GainedVoucherCodeGenerationFailedException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Auto-issues a one-time reward voucher for any order that clears a configurable minimum value -
 * decoupled from gift orders (unlike legacy, which only rewarded placing a gift). Stateless by
 * design - takes an already-resolved GainedVoucherConfiguration rather than reading settings
 * itself, so it's a pure function of its inputs (see GainedVoucherConfiguration's docblock).
 */
final class GainedVoucherService
{
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(
        private readonly GainedVoucherRepository $gainedVoucherRepository,
        private readonly VoucherRepository $voucherRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * No-op (returns null) while disabled or below the threshold - a disabled feature must never
     * generate a code, same reasoning already applied to credit points.
     */
    public function maybeIssue(Order $order, GainedVoucherConfiguration $configuration): ?Voucher
    {
        if (!$configuration->isEnabled() || !$this->meetsMinimumOrderValue($order, $configuration)) {
            return null;
        }
        $voucher = $this->buildVoucher($order, $configuration);
        $this->gainedVoucherRepository->add($voucher);
        $this->persistenceManager->persistAll();
        $this->eventDispatcher->dispatch(new VoucherGeneratedEvent($voucher, $order));
        return $voucher;
    }

    private function meetsMinimumOrderValue(Order $order, GainedVoucherConfiguration $configuration): bool
    {
        return $order->getTotalGross()->getCents() >= $configuration->getMinimumOrderValue()->getCents();
    }

    /**
     * Non-combinable and single-use, since a reward is meant to be one discrete perk, not a
     * stackable discount. Bound to the ordering customer when known; guest orders (frontend_user
     * 0) leave it unbound, matching Voucher::isAvailableToFrontendUser()'s "0 = anyone" semantics.
     */
    private function buildVoucher(Order $order, GainedVoucherConfiguration $configuration): Voucher
    {
        $voucher = new Voucher();
        $voucher->setCode($this->generateUniqueCode());
        $voucher->setTitle(sprintf('Reward for order %s', $order->getOrderNumber()));
        $voucher->setDiscountType($configuration->getRewardType());
        $voucher->setDiscountValue($configuration->getRewardValue());
        $voucher->setCombinable(false);
        $voucher->setUsageLimit(1);
        $voucher->setBoundFrontendUser($order->getFrontendUser());
        $voucher->setGeneratedFromOrder($order->getUid() ?? 0);
        return $voucher;
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
