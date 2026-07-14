<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Voucher;

use GoldeneZeiten\Products\Core\Configuration\GainedVoucherConfiguration;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\Voucher;
use GoldeneZeiten\Products\Core\Domain\Repository\GainedVoucherRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Core\Event\VoucherGeneratedEvent;
use GoldeneZeiten\Products\Core\Service\Voucher\Exception\GainedVoucherCodeGenerationFailedException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Auto-issues one-time reward vouchers for qualifying orders.
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
     * Builds non-combinable, single-use reward bound to customer (unbound for guests).
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
