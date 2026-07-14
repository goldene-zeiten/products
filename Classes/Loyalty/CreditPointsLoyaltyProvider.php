<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Loyalty;

use GoldeneZeiten\Products\Configuration\CreditPointsConfiguration;
use GoldeneZeiten\Products\Configuration\CreditPointsConfigurationFactory;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsRedemption;
use GoldeneZeiten\Products\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Domain\Enum\CreditPointsTransactionType;
use GoldeneZeiten\Products\Domain\Model\CreditPointsTransaction;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsBalanceService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * The credit-points programme, seen through the loyalty contract. It ships with the extension so a shop
 * has loyalty out of the box, but the checkout reaches it only as a loyalty provider, which is what lets
 * it move into its own extension later without the core changing.
 *
 * The earn and redeem bookings used to live in the order creation service; they belong to the programme,
 * so they moved here. The atomic debit still guards against a balance being spent twice.
 */
final class CreditPointsLoyaltyProvider implements LoyaltyProviderInterface
{
    public function __construct(
        private readonly CreditPointsService $creditPointsService,
        private readonly CreditPointsBalanceService $creditPointsBalanceService,
        private readonly CreditPointsTransactionRepository $creditPointsTransactionRepository,
        private readonly CreditPointsConfigurationFactory $creditPointsConfigurationFactory,
        private readonly PersistenceManagerInterface $persistenceManager
    ) {}

    public function getIdentifier(): string
    {
        return CoreAdjustmentProvider::CREDIT_POINTS;
    }

    public function getBalance(LoyaltyContext $context): int
    {
        if (!$this->configuration($context)->isEnabled()) {
            return 0;
        }

        return $this->creditPointsService->getBalance($context->getFrontendUserUid());
    }

    public function assertRedeemable(LoyaltyContext $context): void
    {
        if (!$this->configuration($context)->isEnabled() || $context->getRequestedSpendPoints() <= 0) {
            return;
        }
        $this->creditPointsService->assertSpendable($context->getFrontendUserUid(), $context->getRequestedSpendPoints());
    }

    public function quoteRedemption(LoyaltyContext $context): ?CheckoutAdjustment
    {
        if (!$this->configuration($context)->isEnabled()) {
            return null;
        }
        $redemption = $this->redeem($context);
        if ($redemption->getDiscountAmount()->getCents() === 0) {
            return null;
        }

        return new CheckoutAdjustment(
            AdjustmentType::LOYALTY,
            CoreAdjustmentProvider::CREDIT_POINTS,
            '',
            Money::fromCents(-$redemption->getDiscountAmount()->getCents()),
            0.0,
            ['points' => (string)$redemption->getPoints()]
        );
    }

    public function applyRedemption(Order $order, LoyaltyContext $context): void
    {
        if (!$this->configuration($context)->isEnabled()) {
            return;
        }
        $points = $this->redeem($context)->getPoints();
        if ($points <= 0) {
            return;
        }
        if (!$this->creditPointsBalanceService->debitIfAffordable($context->getFrontendUserUid(), $points)) {
            throw new InsufficientCreditPointsException(
                sprintf('Requested %d credit points but the balance could not afford it at redemption time.', $points),
                1783430100
            );
        }
        $this->creditPointsTransactionRepository->add($this->buildTransaction($order, $context->getFrontendUserUid(), -$points, CreditPointsTransactionType::REDEEM));
        $this->persistenceManager->persistAll();
    }

    public function award(Order $order, LoyaltyContext $context): void
    {
        $configuration = $this->configuration($context);
        if (!$configuration->isEnabled() || $context->getFrontendUserUid() === 0) {
            return;
        }
        $earned = $this->creditPointsService->calculateEarnedPoints($context->getBasketViewModel(), $configuration);
        if ($earned <= 0) {
            return;
        }
        $this->creditPointsBalanceService->credit($context->getFrontendUserUid(), $earned);
        $this->creditPointsTransactionRepository->add($this->buildTransaction($order, $context->getFrontendUserUid(), $earned, CreditPointsTransactionType::EARN));
        $this->persistenceManager->persistAll();
    }

    private function redeem(LoyaltyContext $context): CreditPointsRedemption
    {
        return $this->creditPointsService->redeem(
            $context->getFrontendUserUid(),
            $context->getRequestedSpendPoints(),
            $context->getRemainingGoodsTotal(),
            $this->configuration($context)
        );
    }

    private function configuration(LoyaltyContext $context): CreditPointsConfiguration
    {
        return $this->creditPointsConfigurationFactory->create($context->getRequest());
    }

    private function buildTransaction(Order $order, int $frontendUser, int $points, CreditPointsTransactionType $type): CreditPointsTransaction
    {
        $transaction = new CreditPointsTransaction();
        $transaction->setFrontendUser($frontendUser);
        $transaction->setOrderUid($order->getUid() ?? 0);
        $transaction->setPoints($points);
        $transaction->setType($type);
        $transaction->setCreated(new \DateTime());

        return $transaction;
    }
}
