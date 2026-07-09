<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Withdrawal;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Service\OrderMailService;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * The EU/German statutory right-of-withdrawal self-service flow: a guest or logged-in customer
 * cancels their own order via an emailed/thank-you-page link, without needing BE access - see
 * WithdrawalController for the request-facing side.
 */
final class WithdrawalService
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly OrderStatusManager $orderStatusManager,
        private readonly OrderMailService $orderMailService,
        private readonly PersistenceManagerInterface $persistenceManager,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    /**
     * @throws InvalidWithdrawalTokenException
     */
    public function resolveOrder(int $orderUid, string $hash): Order
    {
        $order = $this->orderRepository->findByUidIgnoringStoragePage($orderUid);
        if ($order === null || !$this->withdrawalTokenService->isValid($order, $hash)) {
            throw new InvalidWithdrawalTokenException(
                sprintf('Invalid or expired withdrawal token for order %d.', $orderUid),
                1752100000
            );
        }
        return $order;
    }

    public function isStillWithdrawable(Order $order): bool
    {
        return $order->getStatus()->canTransitionTo(OrderStatus::CANCELLED) && $this->withinWithdrawalPeriod($order);
    }

    /**
     * @throws WithdrawalEmailMismatchException
     * @throws WithdrawalPeriodExpiredException
     * @throws OrderNotWithdrawableException
     */
    public function withdraw(Order $order, string $email, string $reason): void
    {
        $this->assertEmailMatches($order, $email);
        $this->assertWithinPeriod($order);
        $this->assertCancellable($order);

        $this->orderStatusManager->transition($order, OrderStatus::CANCELLED, $reason);
        $this->orderRepository->update($order);
        $this->persistenceManager->persistAll();
        $this->orderMailService->sendWithdrawalNotification($order, $reason);
    }

    private function assertEmailMatches(Order $order, string $email): void
    {
        if (strcasecmp(trim($email), trim($order->getEmail())) !== 0) {
            throw new WithdrawalEmailMismatchException(
                sprintf('Submitted email does not match order %d.', $order->getUid() ?? 0),
                1752100001
            );
        }
    }

    private function assertWithinPeriod(Order $order): void
    {
        if (!$this->withinWithdrawalPeriod($order)) {
            throw new WithdrawalPeriodExpiredException(
                sprintf('Withdrawal period has expired for order %d.', $order->getUid() ?? 0),
                1752100002
            );
        }
    }

    private function assertCancellable(Order $order): void
    {
        if (!$order->getStatus()->canTransitionTo(OrderStatus::CANCELLED)) {
            throw new OrderNotWithdrawableException(
                sprintf('Order %d can no longer be withdrawn from status "%s".', $order->getUid() ?? 0, $order->getStatus()->value),
                1752100003
            );
        }
    }

    private function withinWithdrawalPeriod(Order $order): bool
    {
        $days = (int)($this->settings['checkout']['withdrawalPeriodDays'] ?? 14);
        $orderDate = $order->getOrderDate();
        if ($days <= 0 || $orderDate === null) {
            return false;
        }

        $deadline = (clone $orderDate)->modify(sprintf('+%d days', $days));
        return $deadline >= new \DateTime();
    }
}
