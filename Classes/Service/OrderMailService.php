<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use GoldeneZeiten\Products\Domain\Model\Order;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Settings\SettingsInterface;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
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
        private readonly SiteFinder $siteFinder
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $this->send($order, 'OrderConfirmation', self::LANGUAGE_FILE . 'order_confirmation_subject', $order->getEmail());
    }

    public function sendMerchantNotification(Order $order): void
    {
        $recipient = $this->getSetting($this->getSettings($order), 'products.email.merchantRecipient', '');
        if ($recipient === '') {
            return;
        }

        $this->send($order, 'MerchantNotification', self::LANGUAGE_FILE . 'merchant_notification_subject', $recipient);
    }

    private function send(Order $order, string $template, string $subjectKey, string $recipient): void
    {
        $settings = $this->getSettings($order);
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

        $this->mailer->send($email);
    }

    private function buildTemplatePaths(SettingsInterface $settings): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(
            $this->getPathsSetting($settings, 'products.email.templateRootPaths', self::DEFAULT_TEMPLATE_ROOT_PATHS)
        );
        $templatePaths->setPartialRootPaths(
            $this->getPathsSetting($settings, 'products.email.partialRootPaths', self::DEFAULT_PARTIAL_ROOT_PATHS)
        );
        $templatePaths->setLayoutRootPaths(
            $this->getPathsSetting($settings, 'products.email.layoutRootPaths', self::DEFAULT_LAYOUT_ROOT_PATHS)
        );

        return $templatePaths;
    }

    /**
     * @param non-empty-array<string> $default
     * @return non-empty-array<string>
     */
    private function getPathsSetting(SettingsInterface $settings, string $path, array $default): array
    {
        return $settings->has($path) ? $settings->get($path) : $default;
    }

    private function getSettings(Order $order): SettingsInterface
    {
        try {
            return $this->siteFinder->getSiteByIdentifier($order->getSiteIdentifier())->getSettings();
        } catch (SiteNotFoundException) {
            return $this->fallbackSettings();
        }
    }

    private function fallbackSettings(): SettingsInterface
    {
        $sites = $this->siteFinder->getAllSites();
        $site = reset($sites);

        return $site !== false ? $site->getSettings() : SiteSettings::createFromSettingsTree([]);
    }

    private function getSetting(SettingsInterface $settings, string $path, string $default): string
    {
        return $settings->has($path) ? (string)$settings->get($path) : $default;
    }
}
