<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class WithdrawalController extends ActionController
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService
    ) {}

    public function formAction(int $order, string $hash): ResponseInterface
    {
        $orderObject = $this->resolveOrderOrNull($order, $hash);
        $this->view->assignMultiple([
            'order' => $orderObject,
            'hash' => $hash,
            'withdrawable' => $orderObject instanceof Order && $this->withdrawalService->isStillWithdrawable($orderObject, $this->request),
        ]);
        return $this->htmlResponse();
    }

    public function confirmAction(int $order, string $hash, string $email, string $reason = ''): ResponseInterface
    {
        $orderObject = $this->resolveOrderOrNull($order, $hash);
        if ($orderObject === null) {
            return $this->redirect('form', null, null, ['order' => $order, 'hash' => $hash]);
        }

        try {
            $this->withdrawalService->withdraw($orderObject, $email, $reason, $this->request);
        } catch (WithdrawalEmailMismatchException|WithdrawalPeriodExpiredException|OrderNotWithdrawableException $exception) {
            $this->addFlashMessage($this->translateExceptionMessage($exception), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('form', null, null, ['order' => $order, 'hash' => $hash]);
        }

        $this->view->assign('order', $orderObject);
        return $this->htmlResponse();
    }

    private function resolveOrderOrNull(int $order, string $hash): ?Order
    {
        try {
            return $this->withdrawalService->resolveOrder($order, $hash);
        } catch (InvalidWithdrawalTokenException) {
            return null;
        }
    }

    private function translateExceptionMessage(\Throwable $exception): string
    {
        $key = match (true) {
            $exception instanceof WithdrawalEmailMismatchException => 'withdrawal_email_mismatch',
            $exception instanceof WithdrawalPeriodExpiredException => 'withdrawal_period_expired',
            default => 'withdrawal_not_allowed',
        };
        return (string)LocalizationUtility::translate($key, 'ProductsCore');
    }
}
