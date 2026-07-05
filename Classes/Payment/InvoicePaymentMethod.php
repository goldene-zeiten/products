<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Event\InvoiceNumberGeneratedEvent;
use GoldeneZeiten\Products\Service\Order\NumberRangeService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class InvoicePaymentMethod implements PaymentMethodInterface
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    public function __construct(
        private readonly NumberRangeService $numberRangeService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {}

    public function getIdentifier(): string
    {
        return 'invoice';
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_invoice', 'Products');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        $invoiceSettings = $this->getSettings()['payment']['invoice'] ?? [];

        return (bool)($invoiceSettings['enabled'] ?? true);
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        $invoiceNumber = (string)$this->numberRangeService->next($this->buildScope($order));
        $order->setInvoiceNumber($invoiceNumber);
        $this->eventDispatcher->dispatch(new InvoiceNumberGeneratedEvent($order, $invoiceNumber));

        return PaymentResult::completed(PaymentStatus::PENDING, $invoiceNumber);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return $this->settings ??= $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    private function buildScope(Order $order): string
    {
        $siteIdentifier = $order->getSiteIdentifier() !== '' ? $order->getSiteIdentifier() : 'default';
        return sprintf('invoice:%s:%s', $siteIdentifier, (new \DateTimeImmutable())->format('Y'));
    }
}
