<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Guest/customer self-service order withdrawal (EU right of withdrawal).
 */
final class WithdrawalService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly OrderStatusManager $orderStatusManager,
        private readonly OrderMailService $orderMailService,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {}

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

    public function isStillWithdrawable(Order $order, ServerRequestInterface $request): bool
    {
        return $order->getStatus()->canTransitionTo(OrderStatus::CANCELLED) && $this->withinWithdrawalPeriod($order, $request);
    }

    /**
     * @throws WithdrawalEmailMismatchException
     * @throws WithdrawalPeriodExpiredException
     * @throws OrderNotWithdrawableException
     */
    public function withdraw(Order $order, string $email, string $reason, ServerRequestInterface $request): void
    {
        $this->assertEmailMatches($order, $email);
        $this->assertWithinPeriod($order, $request);
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

    private function assertWithinPeriod(Order $order, ServerRequestInterface $request): void
    {
        if (!$this->withinWithdrawalPeriod($order, $request)) {
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

    private function withinWithdrawalPeriod(Order $order, ServerRequestInterface $request): bool
    {
        $this->configurationManager->setRequest($request);
        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'ProductsCore'
        );
        $days = (int)($settings['checkout']['withdrawalPeriodDays'] ?? 14);
        $orderDate = $order->getOrderDate();
        if ($days <= 0 || $orderDate === null) {
            return false;
        }

        $deadline = (clone $orderDate)->modify(sprintf('+%d days', $days));
        return $deadline >= new \DateTime();
    }
}
