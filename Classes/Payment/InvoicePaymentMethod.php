<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\InvoiceNumberGeneratedEvent;
use GoldeneZeiten\Products\Service\Order\NumberRangeService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Invoice has no gateway to call back; `cancel()`/`refund()` just acknowledge the money movement
 * happened outside the system.
 */
final class InvoicePaymentMethod implements PaymentMethodInterface, RefundablePaymentMethodInterface
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

    /**
     * The default method every shop falls back to, so it sorts below anything an integrator registers.
     */
    public function getPriority(): int
    {
        return 0;
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

    public function cancel(Order $order): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::FAILED, $order->getInvoiceNumber());
    }

    public function refund(Order $order, Money $amount): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::REFUNDED, $order->getInvoiceNumber());
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
