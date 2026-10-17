<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalStatement;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Model\ShippingPoint;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Event\BeforeOrderMailSentEvent;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Core\Payment\PaymentInstructionsResolver;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Exception\AgbFileNotFoundException;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoicePdfService;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceRenderer;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalExclusionService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalInstructionsBuilder;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalSettingsFactory;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalUrlFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;

final class OrderMailService
{
    private const LANGUAGE_FILE = 'LLL:EXT:products_core/Resources/Private/Language/locallang_email.xlf:';
    private const DEFAULT_TEMPLATE_ROOT_PATHS = ['EXT:products_core/Resources/Private/Templates/Email/'];
    private const DEFAULT_PARTIAL_ROOT_PATHS = ['EXT:products_core/Resources/Private/Partials/'];
    private const DEFAULT_LAYOUT_ROOT_PATHS = ['EXT:products_core/Resources/Private/Layouts/'];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly OrderSettingsResolver $settingsResolver,
        private readonly InvoiceRenderer $invoiceRenderer,
        private readonly InvoicePdfService $invoicePdfService,
        private readonly ProductRepository $productRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly LoggerInterface $logger,
        private readonly WithdrawalUrlFactory $withdrawalUrlFactory,
        private readonly WithdrawalExclusionService $withdrawalExclusionService,
        private readonly WithdrawalSettingsFactory $withdrawalSettingsFactory,
        private readonly WithdrawalInstructionsBuilder $instructionsBuilder,
        private readonly ConnectionPool $connectionPool,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly PaymentInstructionsResolver $paymentInstructionsResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $email = $this->buildEmail($order, 'OrderConfirmation', self::LANGUAGE_FILE . 'order_confirmation_subject', $order->getEmail());
        $this->assignWithdrawalVariables($email, $order);
        $this->attachInvoice($email, $order);
        $this->attachAgb($email, $order);
        $this->send($email, $order, 'OrderConfirmation');
        $this->recordWithdrawalInformation($order);
    }

    /**
     * The confirmation mail is the durable medium carrying the withdrawal instruction: the period,
     * the one-click button to the prefilled form (Art. 11a Directive 2011/83/EU) - or, for orders
     * (partially) made up of Art. 16 exempt items, the notice which items carry no withdrawal right.
     */
    private function assignWithdrawalVariables(FluidEmail $email, Order $order): void
    {
        $withdrawalSettings = $this->withdrawalSettingsFactory->fromSettings($this->settingsResolver->getSettings($order));
        $fullyExcluded = $this->withdrawalExclusionService->isFullyExcluded($order);
        $email->assignMultiple([
            'withdrawalFullyExcluded' => $fullyExcluded,
            'withdrawalExcludedItems' => $this->withdrawalExclusionService->excludedItems($order),
        ]);
        if ($fullyExcluded || $withdrawalSettings->periodDays <= 0) {
            return;
        }
        $email->assignMultiple([
            'withdrawalPeriodDays' => $withdrawalSettings->periodDays,
            'withdrawalUrl' => $this->withdrawalUrlFactory->createFor($order),
            'instructions' => $this->instructionsBuilder->build(
                $withdrawalSettings,
                $this->instructionsBuilder->variantFor($order),
                $this->withdrawalUrlFactory->createFunctionUrlFor($order)
            ),
        ]);
    }

    /**
     * Without the withdrawal information on a durable medium the withdrawal period grows by twelve
     * months (Art. 10), so the moment the confirmation left the shop is recorded on the order.
     */
    private function recordWithdrawalInformation(Order $order): void
    {
        if ($order->getUid() === null || $this->withdrawalExclusionService->isFullyExcluded($order)) {
            return;
        }
        $order->setWithdrawalInformationProvidedAt(new \DateTime());
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder
            ->update('tx_products_domain_model_order')
            ->set('withdrawal_information_provided_at', time())
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($order->getUid(), Connection::PARAM_INT)))
            ->executeStatement();
    }

    /**
     * The global recipient (if configured) always gets notified; each category- and
     * shipping-point-specific recipient resolved from the order's line items is notified in
     * addition, not instead of it - one recipient per address, with both routing axes firing
     * independently.
     */
    public function sendMerchantNotification(Order $order): void
    {
        $recipients = $this->resolveMerchantRecipients($order);
        foreach ($recipients as $recipient) {
            $this->send($this->buildEmail($order, 'MerchantNotification', self::LANGUAGE_FILE . 'merchant_notification_subject', $recipient), $order, 'MerchantNotification');
        }
    }

    /**
     * @return string[]
     */
    private function resolveMerchantRecipients(Order $order): array
    {
        $globalRecipient = $this->getSetting($this->settingsResolver->getSettings($order), 'products.email.merchantRecipient', '');
        $recipients = $globalRecipient !== '' ? [$globalRecipient] : [];
        return array_values(array_unique(array_merge(
            $recipients,
            $this->resolveCategoryNotificationRecipients($order),
            $this->resolveShippingPointNotificationRecipients($order)
        )));
    }

    /**
     * Each category is resolved at most once per order (mirrors legacy's per-category dedup
     * cache), then each distinct email address at most once regardless of how many categories
     * share it.
     *
     * @return string[]
     */
    private function resolveCategoryNotificationRecipients(Order $order): array
    {
        $seenCategories = [];
        $recipients = [];
        foreach ($order->getItems() as $item) {
            foreach ($this->categoriesForItem($item) as $category) {
                $categoryUid = $category->getUid();
                if ($categoryUid === null || isset($seenCategories[$categoryUid])) {
                    continue;
                }
                $seenCategories[$categoryUid] = true;
                $email = $category->getNotificationEmail();
                if ($email !== '') {
                    $recipients[$email] = $email;
                }
            }
        }
        return array_values($recipients);
    }

    /**
     * @return Category[]
     */
    private function categoriesForItem(OrderItem $item): array
    {
        $product = $this->productRepository->findByUid($item->getProduct());
        return $product instanceof Product ? $product->getCategories()->toArray() : [];
    }

    /**
     * Independent of, and dedupes separately from, the per-category routing above - a product's
     * shipping point and its categories are unrelated axes and both can fire for the same order,
     * matching legacy behaviour.
     *
     * @return string[]
     */
    private function resolveShippingPointNotificationRecipients(Order $order): array
    {
        $seenShippingPoints = [];
        $recipients = [];
        foreach ($order->getItems() as $item) {
            $shippingPoint = $this->shippingPointForItem($item);
            $shippingPointUid = $shippingPoint?->getUid();
            if ($shippingPointUid === null || isset($seenShippingPoints[$shippingPointUid])) {
                continue;
            }
            $seenShippingPoints[$shippingPointUid] = true;
            $email = $shippingPoint->getNotificationEmail();
            if ($email !== '') {
                $recipients[$email] = $email;
            }
        }
        return array_values($recipients);
    }

    private function shippingPointForItem(OrderItem $item): ?ShippingPoint
    {
        $product = $this->productRepository->findByUid($item->getProduct());
        return $product instanceof Product ? $product->getShippingPoint() : null;
    }

    public function sendOrderStatusChanged(Order $order, OrderStatus $previousStatus, OrderStatus $newStatus): void
    {
        $settings = $this->settingsResolver->getSettings($order);
        if (!$this->getBoolSetting($settings, 'products.email.orderStatusChanged.enabled', true)) {
            return;
        }

        $email = $this->buildEmail($order, 'OrderStatusChanged', self::LANGUAGE_FILE . 'order_status_changed_subject', $order->getEmail());
        $email->assignMultiple([
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'previousStatusLabel' => $this->statusLabel($previousStatus),
            'newStatusLabel' => $this->statusLabel($newStatus),
        ]);
        $this->send($email, $order, 'OrderStatusChanged');
    }

    /**
     * Fired directly from WithdrawalService on a successful self-service withdrawal - not routed
     * through OrderStatusChangedEvent/sendOrderStatusChanged() (which only notifies the customer)
     * because the merchant specifically needs to know a *customer-initiated* withdrawal happened and
     * which items it covers. Reuses the same recipient resolution as a new order (global + per-category).
     *
     * @param OrderItem[] $withdrawnItems
     */
    public function sendWithdrawalNotification(Order $order, WithdrawalStatement $statement, array $withdrawnItems): void
    {
        foreach ($this->resolveMerchantRecipients($order) as $recipient) {
            $email = $this->buildEmail($order, 'Withdrawal', self::LANGUAGE_FILE . 'withdrawal_subject', $recipient);
            $email->assignMultiple([
                'statement' => $statement,
                'withdrawnItems' => $withdrawnItems,
                'submittedAt' => $this->formatSubmission($order, $statement),
                'awaitingReturn' => $order->getStatus() === OrderStatus::WITHDRAWN,
                'withdrawalExcludedItems' => $this->withdrawalExclusionService->excludedItems($order),
            ]);
            $this->send($email, $order, 'Withdrawal');
        }
    }

    /**
     * The acknowledgement of receipt on a durable medium that Art. 11a(4) Directive 2011/83/EU requires
     * without undue delay: the content of the statement, the date and time of its submission, what
     * happens to the goods and the refund.
     *
     * @param OrderItem[] $withdrawnItems
     */
    public function sendWithdrawalAcknowledgement(Order $order, WithdrawalStatement $statement, array $withdrawnItems): void
    {
        $withdrawalSettings = $this->withdrawalSettingsFactory->fromSettings($this->settingsResolver->getSettings($order));
        $email = $this->buildEmail($order, 'WithdrawalAcknowledgement', self::LANGUAGE_FILE . 'withdrawal_acknowledgement_subject', $statement->email);
        $email->assignMultiple([
            'statement' => $statement,
            'withdrawnItems' => $withdrawnItems,
            'submittedAt' => $this->formatSubmission($order, $statement),
            'returnRequired' => $order->getStatus() === OrderStatus::WITHDRAWN,
            'returnAndRefundDeadline' => $statement->submittedAt->setTimezone($withdrawalSettings->getDateTimeZone())->modify('+14 days')->format('Y-m-d'),
            'trader' => $withdrawalSettings->trader,
            'returnCostBorneByTrader' => $withdrawalSettings->returnCostBorneByTrader,
            'conditionalItems' => $this->itemsLostByUnsealing($withdrawnItems),
            'instructions' => $this->instructionsBuilder->build(
                $withdrawalSettings,
                $this->instructionsBuilder->variantFor($order),
                $this->withdrawalUrlFactory->createFunctionUrlFor($order)
            ),
        ]);
        $this->send($email, $order, 'WithdrawalAcknowledgement');
    }

    /**
     * @param OrderItem[] $items
     * @return OrderItem[]
     */
    private function itemsLostByUnsealing(array $items): array
    {
        return array_values(array_filter($items, static fn(OrderItem $item): bool => $item->getWithdrawalExclusion()->isLostByUnsealing()));
    }

    /**
     * The submission moment in the shop's time zone, so the customer reads the same time on the
     * acknowledgement as on the clock of the shop that received the statement.
     */
    private function formatSubmission(Order $order, WithdrawalStatement $statement): string
    {
        $withdrawalSettings = $this->withdrawalSettingsFactory->fromSettings($this->settingsResolver->getSettings($order));
        $format = (string)LocalizationUtility::translate(self::LANGUAGE_FILE . 'withdrawal_datetime_format', 'ProductsCore');
        return $statement->submittedAt->setTimezone($withdrawalSettings->getDateTimeZone())->format($format !== '' ? $format : 'Y-m-d H:i:s T');
    }

    public function sendLowStockWarning(string $title, int $newStock): void
    {
        $settings = $this->settingsResolver->getDefaultSettings();
        $recipient = $this->getSetting($settings, 'products.email.merchantRecipient', '');
        if ($recipient === '') {
            return;
        }

        $email = new FluidEmail($this->buildTemplatePaths($settings));
        $this->applySender($email, $settings);
        $email
            ->to($recipient)
            ->subject((string)LocalizationUtility::translate(self::LANGUAGE_FILE . 'low_stock_warning_subject', 'ProductsCore', [$title]))
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate('LowStockWarning')
            ->assignMultiple(['title' => $title, 'newStock' => $newStock]);
        $this->mailer->send($email);
    }

    private function statusLabel(OrderStatus $status): string
    {
        $key = match ($status) {
            OrderStatus::NEW => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:order.status.new',
            OrderStatus::PENDING => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:order.status.pending',
            OrderStatus::CONFIRMED => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:order.status.confirmed',
            OrderStatus::CANCELLED => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:order.status.cancelled',
            OrderStatus::SHIPPED => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:order.status.shipped',
            OrderStatus::COMPLETED => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:order.status.completed',
            OrderStatus::WITHDRAWN => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:order.status.withdrawn',
        };
        return (string)(LocalizationUtility::translate($key) ?? $status->value);
    }

    private function buildEmail(Order $order, string $template, string $subjectKey, string $recipient): FluidEmail
    {
        $settings = $this->settingsResolver->getSettings($order);
        $email = new FluidEmail($this->buildTemplatePaths($settings));
        $this->applySender($email, $settings);
        $this->applyBcc($email, $settings);

        $email
            ->to($recipient)
            ->subject((string)LocalizationUtility::translate($subjectKey, 'ProductsCore', [$order->getOrderNumber()]))
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate($template)
            ->assignMultiple([
                'order' => $order,
                'moneyLocale' => $this->settingsResolver->getLocale($order),
                ...$this->paymentVariables($order),
            ]);

        return $email;
    }

    /**
     * A migrated order may name a payment method that is no longer installed; its identifier is shown then.
     *
     * @return array{paymentMethodLabel: string, paymentInstructions: string}
     */
    private function paymentVariables(Order $order): array
    {
        try {
            $paymentMethod = $this->paymentMethodRegistry->get($order->getPaymentMethod());
        } catch (PaymentMethodNotFoundException) {
            return [
                'paymentMethodLabel' => $order->getPaymentMethod(),
                'paymentInstructions' => '',
            ];
        }
        return [
            'paymentMethodLabel' => $paymentMethod->getLabel(),
            'paymentInstructions' => $this->paymentInstructionsResolver->resolve($order),
        ];
    }

    private function send(FluidEmail $email, Order $order, string $template): void
    {
        $this->eventDispatcher->dispatch(new BeforeOrderMailSentEvent($email, $order, $template));
        $this->mailer->send($email);
    }

    /**
     * A standing blind-copy recipient (e.g. accounting/archival/compliance) for every order email
     * built through here - order confirmation, merchant notification, status-changed, withdrawal.
     */
    private function applyBcc(FluidEmail $email, SettingsInterface $settings): void
    {
        $bccRecipient = $this->getSetting($settings, 'products.email.orderBccRecipient', '');
        if ($bccRecipient !== '') {
            $email->bcc($bccRecipient);
        }
    }

    private function applySender(FluidEmail $email, SettingsInterface $settings): void
    {
        $senderEmail = $this->getSetting($settings, 'products.email.senderEmail', '');
        if ($senderEmail !== '') {
            $email->from(new Address($senderEmail, $this->getSetting($settings, 'products.email.senderName', '')));
        }
    }

    /**
     * A failed invoice render/PDF-attach must never prevent the confirmation email itself from
     * being sent - the invoice can still be re-downloaded later via the tracking-code link.
     */
    private function attachInvoice(FluidEmail $email, Order $order): void
    {
        try {
            $pdf = $this->invoicePdfService->renderToPdf($order, $this->invoiceRenderer->render($order));
            $email->attach($pdf, sprintf('invoice-%s.pdf', $order->getInvoiceNumber()), 'application/pdf');
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to attach invoice PDF for order %s.', $order->getOrderNumber()),
                ['exception' => $exception]
            );
        }
    }

    /**
     * A missing/misconfigured AGB file must never prevent the confirmation email itself from
     * being sent, same reasoning as attachInvoice().
     */
    private function attachAgb(FluidEmail $email, Order $order): void
    {
        $identifier = $this->getSetting($this->settingsResolver->getSettings($order), 'products.email.agbAttachment', '');
        if ($identifier === '') {
            return;
        }
        try {
            $file = $this->resourceFactory->getFileObjectFromCombinedIdentifier($identifier);
            if ($file === null) {
                throw new AgbFileNotFoundException(sprintf('AGB file "%s" not found.', $identifier), 1783675922);
            }
            $email->attach($file->getContents(), $file->getName(), $file->getMimeType());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to attach AGB file "%s" for order %s.', $identifier, $order->getOrderNumber()),
                ['exception' => $exception]
            );
        }
    }

    private function buildTemplatePaths(SettingsInterface $settings): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(
            $this->settingsResolver->getPathsSetting($settings, 'products.email.templateRootPaths', self::DEFAULT_TEMPLATE_ROOT_PATHS)
        );
        $templatePaths->setPartialRootPaths(
            $this->settingsResolver->getPathsSetting($settings, 'products.email.partialRootPaths', self::DEFAULT_PARTIAL_ROOT_PATHS)
        );
        $templatePaths->setLayoutRootPaths(
            $this->settingsResolver->getPathsSetting($settings, 'products.email.layoutRootPaths', self::DEFAULT_LAYOUT_ROOT_PATHS)
        );

        return $templatePaths;
    }

    private function getSetting(SettingsInterface $settings, string $path, string $default): string
    {
        return $settings->has($path) ? (string)$settings->get($path) : $default;
    }

    private function getBoolSetting(SettingsInterface $settings, string $path, bool $default): bool
    {
        return $settings->has($path) ? (bool)$settings->get($path) : $default;
    }
}
