<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalStatement;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalRequestException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\TooManyWithdrawalAttemptsException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalNameMissingException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalNoItemSelectedException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalInstructionsBuilder;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalRateLimiter;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalSettingsFactory;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The Art. 11a (Directive 2011/83/EU, inserted by Directive (EU) 2023/2673) withdrawal function.
 * The consumer reaches it in one of three ways: from an order link (confirmation mail, thank-you page,
 * order history) that carries the order uid plus its HMAC capability token; logged in, where the
 * customer's own withdrawable orders are listed and need no further identification; or plain (footer
 * navigation, the withdrawal page type), identifying the order by order number + email. The plain
 * identification pair is only ever transported in the POST body - the links use the token precisely so
 * no personal data sits in a GET URL.
 */
final class WithdrawalController extends ActionController
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
        private readonly WithdrawalRateLimiter $withdrawalRateLimiter,
        private readonly WithdrawalSettingsFactory $withdrawalSettingsFactory,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly WithdrawalInstructionsBuilder $instructionsBuilder
    ) {}

    public function formAction(?int $order = null, ?string $hash = null): ResponseInterface
    {
        $settings = $this->withdrawalSettings();
        $now = new \DateTimeImmutable();
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $tokenPresented = $order !== null && $hash !== null;
        $orderObject = $this->resolveOrderOrNull($order, $hash, $frontendUserUid);
        $this->view->assignMultiple([
            'hash' => (string)$hash,
            'invalidToken' => $tokenPresented && $orderObject === null,
            'mode' => $hash !== null ? 'token' : 'customer',
            'customerOrders' => $orderObject === null && $frontendUserUid > 0
                ? $this->withdrawalService->withdrawableOrdersOf($frontendUserUid, $settings, $now)
                : [],
            'trader' => $settings->trader,
        ]);
        $this->assignStatementVariables($orderObject, $settings, $now);
        $this->assignInstructions($settings, $orderObject);
        return $this->htmlResponse();
    }

    /**
     * The model instructions on withdrawal and the model withdrawal form, for a terms page.
     */
    public function instructionsAction(): ResponseInterface
    {
        $this->assignInstructions($this->withdrawalSettings(), null);
        return $this->htmlResponse();
    }

    /**
     * Second step of the plain mode: the order is identified by number + email, then the consumer
     * picks the items and confirms.
     */
    public function selectAction(string $orderNumber, string $email, string $name = ''): ResponseInterface
    {
        try {
            $this->assertWithinRateLimit();
            $orderObject = $this->withdrawalService->resolveOrderByNumberAndEmail($orderNumber, $email);
        } catch (TooManyWithdrawalAttemptsException|InvalidWithdrawalRequestException $exception) {
            $this->addFlashMessage($this->translateExceptionMessage($exception), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('form');
        }
        $this->view->assignMultiple([
            'mode' => 'plain',
            'name' => trim($name) !== '' ? trim($name) : $orderObject->getBillingName(),
            'trader' => $this->withdrawalSettings()->trader,
        ]);
        $this->assignStatementVariables($orderObject, $this->withdrawalSettings(), new \DateTimeImmutable(), false);
        $this->assignInstructions($this->withdrawalSettings(), $orderObject);
        return $this->htmlResponse();
    }

    /**
     * @param int[] $items
     */
    public function confirmAction(
        string $email,
        string $name = '',
        string $orderNumber = '',
        string $reason = '',
        int $order = 0,
        string $hash = '',
        array $items = [],
        bool $itemSelection = false
    ): ResponseInterface {
        $tokenMode = $order > 0 && $hash !== '';
        try {
            $this->assertWithinRateLimit();
            $orderObject = $this->resolveForConfirm($email, $orderNumber, $order, $hash);
            $statement = new WithdrawalStatement(
                $name,
                $email,
                $reason,
                $itemSelection ? array_map(intval(...), $items) : null,
                new \DateTimeImmutable()
            );
            $this->withdrawalService->withdraw($orderObject, $statement, $this->withdrawalSettings());
        } catch (TooManyWithdrawalAttemptsException|InvalidWithdrawalTokenException|InvalidWithdrawalRequestException|WithdrawalEmailMismatchException|WithdrawalPeriodExpiredException|OrderNotWithdrawableException|WithdrawalNameMissingException|WithdrawalNoItemSelectedException $exception) {
            $this->addFlashMessage($this->translateExceptionMessage($exception), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('form', null, null, $tokenMode ? ['order' => $order, 'hash' => $hash] : []);
        }

        $this->view->assignMultiple([
            'order' => $orderObject,
            'statement' => $statement,
            'submittedAt' => $this->formatSubmission($statement),
        ]);
        return $this->htmlResponse();
    }

    /**
     * Every confirmation attempt consumes rate-limit budget, token or not: the plain pair must not
     * be probeable for existing order numbers, and a leaked token link must not be usable to
     * brute-force the matching email.
     *
     * @throws TooManyWithdrawalAttemptsException
     */
    private function assertWithinRateLimit(): void
    {
        if (!$this->withdrawalRateLimiter->isWithinLimit($this->request)) {
            throw new TooManyWithdrawalAttemptsException(
                'Withdrawal attempt limit exceeded for this client.',
                1784937603
            );
        }
    }

    /**
     * A logged-in customer withdraws without any token or order number; because the cookie alone
     * authorises that, it only counts when posted, never when a link was followed.
     */
    private function resolveForConfirm(string $email, string $orderNumber, int $order, string $hash): Order
    {
        if ($order > 0 && $hash !== '') {
            return $this->withdrawalService->resolveOrder($order, $hash);
        }
        if ($order > 0 && $orderNumber === '') {
            return $this->resolveCustomerOrder($order);
        }
        return $this->withdrawalService->resolveOrderByNumberAndEmail($orderNumber, $email);
    }

    private function resolveCustomerOrder(int $order): Order
    {
        $frontendUserUid = $this->request->getMethod() === 'POST' ? $this->frontendUserResolver->getUid($this->request) : 0;
        return $this->withdrawalService->resolveOrderForCustomer($order, $frontendUserUid);
    }

    private function resolveOrderOrNull(?int $order, ?string $hash, int $frontendUserUid): ?Order
    {
        if ($order === null) {
            return null;
        }
        try {
            return $hash !== null
                ? $this->withdrawalService->resolveOrder($order, $hash)
                : $this->withdrawalService->resolveOrderForCustomer($order, $frontendUserUid);
        } catch (InvalidWithdrawalTokenException|InvalidWithdrawalRequestException) {
            return null;
        }
    }

    private function assignStatementVariables(?Order $order, WithdrawalSettings $settings, \DateTimeImmutable $now, bool $assignName = true): void
    {
        $this->view->assign('order', $order);
        if (!$order instanceof Order) {
            return;
        }
        $itemStates = $this->withdrawalService->itemStates($order, $settings, $now);
        $this->view->assignMultiple([
            'itemStates' => $itemStates,
            'withdrawable' => $this->withdrawalService->isStillWithdrawable($order, $settings, $now),
        ]);
        if ($assignName) {
            $this->view->assign('name', $order->getBillingName());
        }
    }

    private function assignInstructions(WithdrawalSettings $settings, ?Order $order): void
    {
        $variant = $order instanceof Order ? $this->instructionsBuilder->variantFor($order) : $settings->instructionVariant;
        $this->view->assign('instructions', $this->instructionsBuilder->build($settings, $variant, $this->withdrawalFunctionUrl()));
    }

    /**
     * Where the online withdrawal function lives: the configured withdrawal page, else the page showing
     * the current plugin.
     */
    private function withdrawalFunctionUrl(): string
    {
        $site = $this->request->getAttribute('site');
        $pageUid = $site instanceof Site ? (int)$site->getSettings()->get('products.pids.withdrawalPage', 0) : 0;
        $routing = $this->request->getAttribute('routing');
        $pageUid = $pageUid > 0 ? $pageUid : ($routing instanceof PageArguments ? $routing->getPageId() : 0);
        if ($pageUid === 0) {
            return '';
        }
        return $this->uriBuilder->reset()->setTargetPageUid($pageUid)->setCreateAbsoluteUri(true)->build();
    }

    private function formatSubmission(WithdrawalStatement $statement): string
    {
        $format = (string)LocalizationUtility::translate('withdrawal_datetime_format', 'ProductsCore');
        return $statement->submittedAt->setTimezone($this->withdrawalSettings()->getDateTimeZone())->format($format !== '' ? $format : 'Y-m-d H:i:s T');
    }

    private function withdrawalSettings(): WithdrawalSettings
    {
        $site = $this->request->getAttribute('site');
        return $this->withdrawalSettingsFactory->fromSettings($site instanceof Site ? $site->getSettings() : $this->emptySettings());
    }

    private function emptySettings(): SettingsInterface
    {
        return SiteSettings::createFromSettingsTree([]);
    }

    private function translateExceptionMessage(\Throwable $exception): string
    {
        $key = match (true) {
            $exception instanceof TooManyWithdrawalAttemptsException => 'withdrawal_too_many_attempts',
            $exception instanceof InvalidWithdrawalTokenException => 'invalid_or_expired_link',
            $exception instanceof InvalidWithdrawalRequestException => 'withdrawal_request_invalid',
            $exception instanceof WithdrawalEmailMismatchException => 'withdrawal_email_mismatch',
            $exception instanceof WithdrawalPeriodExpiredException => 'withdrawal_period_expired',
            $exception instanceof WithdrawalNameMissingException => 'withdrawal_name_missing',
            $exception instanceof WithdrawalNoItemSelectedException => 'withdrawal_no_item_selected',
            default => 'withdrawal_not_allowed',
        };
        return (string)LocalizationUtility::translate($key, 'ProductsCore');
    }
}
