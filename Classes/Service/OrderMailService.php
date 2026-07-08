<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\Invoice\InvoicePdfService;
use GoldeneZeiten\Products\Service\Invoice\InvoiceRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;

final class OrderMailService
{
    private const LANGUAGE_FILE = 'LLL:EXT:products/Resources/Private/Language/locallang_email.xlf:';
    private const DEFAULT_TEMPLATE_ROOT_PATHS = ['EXT:products/Resources/Private/Templates/Email/'];
    private const DEFAULT_PARTIAL_ROOT_PATHS = ['EXT:products/Resources/Private/Partials/'];
    private const DEFAULT_LAYOUT_ROOT_PATHS = ['EXT:products/Resources/Private/Layouts/'];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly OrderSettingsResolver $settingsResolver,
        private readonly InvoiceRenderer $invoiceRenderer,
        private readonly InvoicePdfService $invoicePdfService,
        private readonly LoggerInterface $logger
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $email = $this->buildEmail($order, 'OrderConfirmation', self::LANGUAGE_FILE . 'order_confirmation_subject', $order->getEmail());
        $this->attachInvoice($email, $order);
        $this->mailer->send($email);
    }

    public function sendMerchantNotification(Order $order): void
    {
        $recipient = $this->getSetting($this->settingsResolver->getSettings($order), 'products.email.merchantRecipient', '');
        if ($recipient === '') {
            return;
        }

        $this->mailer->send($this->buildEmail($order, 'MerchantNotification', self::LANGUAGE_FILE . 'merchant_notification_subject', $recipient));
    }

    private function buildEmail(Order $order, string $template, string $subjectKey, string $recipient): FluidEmail
    {
        $settings = $this->settingsResolver->getSettings($order);
        $email = new FluidEmail($this->buildTemplatePaths($settings));

        $senderEmail = $this->getSetting($settings, 'products.email.senderEmail', '');
        if ($senderEmail !== '') {
            $email->from(new Address($senderEmail, $this->getSetting($settings, 'products.email.senderName', '')));
        }

        $email
            ->to($recipient)
            ->subject((string)LocalizationUtility::translate($subjectKey, 'Products', [$order->getOrderNumber()]))
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate($template)
            ->assign('order', $order);

        return $email;
    }

    /**
     * A failed invoice render/PDF-attach must never prevent the confirmation email itself from
     * being sent - the invoice can still be re-downloaded later via the tracking-code link.
     */
    private function attachInvoice(FluidEmail $email, Order $order): void
    {
        try {
            $pdf = $this->invoicePdfService->renderToPdf($this->invoiceRenderer->render($order));
            $email->attach($pdf, sprintf('invoice-%s.pdf', $order->getInvoiceNumber()), 'application/pdf');
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to attach invoice PDF for order %s.', $order->getOrderNumber()),
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
}
