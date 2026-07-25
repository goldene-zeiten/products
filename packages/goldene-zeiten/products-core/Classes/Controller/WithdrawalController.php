<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalRequestException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\TooManyWithdrawalAttemptsException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalRateLimiter;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The Art. 11a (Directive 2011/83/EU, inserted by Directive (EU) 2023/2673) withdrawal function.
 * The form works in two modes: opened from an order link (confirmation mail, thank-you page,
 * order history) it carries the order uid plus its HMAC capability token and comes prefilled;
 * opened plain (footer navigation, the withdrawal page type) the customer identifies the order
 * by order number + email instead. The plain identification pair is only ever transported in the
 * POST body - the links use the token precisely so no personal data sits in a GET URL.
 */
final class WithdrawalController extends ActionController
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
        private readonly WithdrawalRateLimiter $withdrawalRateLimiter
    ) {}

    public function formAction(?int $order = null, ?string $hash = null): ResponseInterface
    {
        $tokenPresented = $order !== null && $hash !== null;
        $orderObject = $tokenPresented ? $this->resolveOrderOrNull($order, $hash) : null;
        $this->view->assignMultiple([
            'order' => $orderObject,
            'hash' => (string)$hash,
            'invalidToken' => $tokenPresented && $orderObject === null,
            'withdrawable' => $orderObject instanceof Order && $this->withdrawalService->isStillWithdrawable($orderObject, $this->request),
        ]);
        return $this->htmlResponse();
    }

    public function confirmAction(string $email, string $orderNumber = '', string $reason = '', int $order = 0, string $hash = ''): ResponseInterface
    {
        $tokenMode = $order > 0 && $hash !== '';
        try {
            $orderObject = $this->resolveForConfirm($email, $orderNumber, $order, $hash);
            $this->withdrawalService->withdraw($orderObject, $email, $reason, $this->request);
        } catch (TooManyWithdrawalAttemptsException|InvalidWithdrawalTokenException|InvalidWithdrawalRequestException|WithdrawalEmailMismatchException|WithdrawalPeriodExpiredException|OrderNotWithdrawableException $exception) {
            $this->addFlashMessage($this->translateExceptionMessage($exception), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('form', null, null, $tokenMode ? ['order' => $order, 'hash' => $hash] : []);
        }

        $this->view->assign('order', $orderObject);
        return $this->htmlResponse();
    }

    /**
     * Every confirmation attempt consumes rate-limit budget, token or not: the plain pair must not
     * be probeable for existing order numbers, and a leaked token link must not be usable to
     * brute-force the matching email.
     */
    private function resolveForConfirm(string $email, string $orderNumber, int $order, string $hash): Order
    {
        if (!$this->withdrawalRateLimiter->isWithinLimit($this->request)) {
            throw new TooManyWithdrawalAttemptsException(
                'Withdrawal attempt limit exceeded for this client.',
                1784937603
            );
        }
        if ($order > 0 && $hash !== '') {
            return $this->withdrawalService->resolveOrder($order, $hash);
        }
        return $this->withdrawalService->resolveOrderByNumberAndEmail($orderNumber, $email);
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
            $exception instanceof TooManyWithdrawalAttemptsException => 'withdrawal_too_many_attempts',
            $exception instanceof InvalidWithdrawalTokenException => 'invalid_or_expired_link',
            $exception instanceof InvalidWithdrawalRequestException => 'withdrawal_request_invalid',
            $exception instanceof WithdrawalEmailMismatchException => 'withdrawal_email_mismatch',
            $exception instanceof WithdrawalPeriodExpiredException => 'withdrawal_period_expired',
            default => 'withdrawal_not_allowed',
        };
        return (string)LocalizationUtility::translate($key, 'ProductsCore');
    }
}
