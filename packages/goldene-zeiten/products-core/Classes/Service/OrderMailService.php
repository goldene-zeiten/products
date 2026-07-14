<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Model\ShippingPoint;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Service\Exception\AgbFileNotFoundException;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoicePdfService;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
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
        private readonly LoggerInterface $logger
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $email = $this->buildEmail($order, 'OrderConfirmation', self::LANGUAGE_FILE . 'order_confirmation_subject', $order->getEmail());
        $settings = $this->settingsResolver->getSettings($order);
        $days = $this->getIntSetting($settings, 'products.checkout.withdrawalPeriodDays', 14);
        if ($days > 0 && $order->getOrderDate() !== null) {
            $deadline = (clone $order->getOrderDate())->modify(sprintf('+%d days', $days));
            $email->assignMultiple(['withdrawalPeriodDays' => $days, 'withdrawalDeadline' => $deadline]);
        }
        $this->attachInvoice($email, $order);
        $this->attachAgb($email, $order);
        $this->mailer->send($email);
    }

    /**
     * The global recipient (if configured) always gets notified; each category- and
     * shipping-point-specific recipient resolved from the order's line items is notified in
     * addition, not instead of it - a simplification of legacy's per-suffix template-bucket
     * system, one recipient per address, with both routing axes firing independently.
     */
    public function sendMerchantNotification(Order $order): void
    {
        $recipients = $this->resolveMerchantRecipients($order);
        foreach ($recipients as $recipient) {
            $this->mailer->send($this->buildEmail($order, 'MerchantNotification', self::LANGUAGE_FILE . 'merchant_notification_subject', $recipient));
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
        $email->assignMultiple(['previousStatus' => $previousStatus, 'newStatus' => $newStatus]);
        $this->mailer->send($email);
    }

    /**
     * Fired directly from WithdrawalService on a successful self-service cancellation - not
     * routed through OrderStatusChangedEvent/sendOrderStatusChanged() (which only notifies the
     * customer) because the merchant specifically needs to know a *customer-initiated*
     * cancellation happened, mirroring legacy's WithdrawalController sending to both shop and
     * customer. Reuses the same recipient resolution as a new order (global + per-category).
     */
    public function sendWithdrawalNotification(Order $order, string $reason): void
    {
        foreach ($this->resolveMerchantRecipients($order) as $recipient) {
            $email = $this->buildEmail($order, 'Withdrawal', self::LANGUAGE_FILE . 'withdrawal_subject', $recipient);
            $email->assign('reason', $reason);
            $this->mailer->send($email);
        }
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
            ->assign('order', $order);

        return $email;
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

    private function getIntSetting(SettingsInterface $settings, string $path, int $default): int
    {
        return $settings->has($path) ? (int)$settings->get($path) : $default;
    }
}
