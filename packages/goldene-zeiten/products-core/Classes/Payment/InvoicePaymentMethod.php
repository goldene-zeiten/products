<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\InvoiceNumberGeneratedEvent;
use GoldeneZeiten\Products\Core\Service\Order\NumberRangeService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Invoice has no gateway to call back; `cancel()`/`refund()` just acknowledge the money movement
 * happened outside the system.
 */
final class InvoicePaymentMethod implements PaymentMethodInterface, RefundablePaymentMethodInterface
{
    public function __construct(
        private readonly NumberRangeService $numberRangeService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function getIdentifier(): string
    {
        return 'invoice';
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_invoice', 'ProductsCore');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $site = $request instanceof ServerRequestInterface ? $request->getAttribute('site') : null;

        return !$site instanceof SiteInterface || (bool)$site->getSettings()->get('products.payment.invoice.enabled', true);
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

    public function cancel(OrderData $order): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::FAILED, $order->invoiceNumber);
    }

    public function refund(OrderData $order, Money $amount): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::REFUNDED, $order->invoiceNumber);
    }

    private function buildScope(Order $order): string
    {
        $siteIdentifier = $order->getSiteIdentifier() !== '' ? $order->getSiteIdentifier() : 'default';
        return sprintf('invoice:%s:%s', $siteIdentifier, (new \DateTimeImmutable())->format('Y'));
    }
}
