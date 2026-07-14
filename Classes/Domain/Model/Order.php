<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

#[Exclude]
class Order extends AbstractEntity
{
    protected string $orderNumber = '';
    protected ?\DateTime $orderDate = null;
    protected int $frontendUser = 0;
    protected string $email = '';
    protected ?OrderAddress $billingAddress = null;
    protected ?OrderAddress $deliveryAddress = null;
    protected string $paymentMethod = '';
    protected PaymentStatus $paymentStatus = PaymentStatus::OPEN;
    protected OrderStatus $status = OrderStatus::NEW;
    protected string $invoiceNumber = '';
    protected string $currency = 'EUR';
    /** @var int */
    protected int $totalNet = 0;
    /** @var int */
    protected int $totalTax = 0;
    /** @var int */
    protected int $totalGross = 0;
    protected string $taxCountry = '';
    /** @var string */
    protected string $taxBreakdown = '[]';
    /** @var string */
    protected string $statusLog = '[]';
    /** @var int */
    protected int $discountTotal = 0;
    /** @var string */
    protected string $voucherCodes = '[]';
    protected string $shippingProvider = '';
    protected string $shippingOption = '';
    protected string $shippingLabel = '';
    /** @var int */
    protected int $shippingTotal = 0;
    /** @var int */
    protected int $handlingFeeTotal = 0;
    /** @var int */
    protected int $depositTotal = 0;
    /**
     * @var ObjectStorage<OrderItem>
     */
    protected ObjectStorage $items;
    protected string $customerNote = '';
    protected string $giftMessage = '';
    protected ?\DateTime $termsAcceptedAt = null;
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $termsDocument;
    protected string $siteIdentifier = '';
    /** @var string */
    protected string $legacyOrderData = '[]';
    protected string $legacyCountryName = '';

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->items = new ObjectStorage();
        $this->termsDocument = new ObjectStorage();
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getOrderDate(): ?\DateTime
    {
        return $this->orderDate;
    }

    public function setOrderDate(?\DateTime $orderDate): void
    {
        $this->orderDate = $orderDate;
    }

    public function getFrontendUser(): int
    {
        return $this->frontendUser;
    }

    public function setFrontendUser(int $frontendUser): void
    {
        $this->frontendUser = $frontendUser;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getBillingAddress(): ?OrderAddress
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?OrderAddress $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    public function getDeliveryAddress(): ?OrderAddress
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?OrderAddress $deliveryAddress): void
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(PaymentStatus $paymentStatus): void
    {
        $this->paymentStatus = $paymentStatus;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getTotalNet(): Money
    {
        return Money::fromCents($this->totalNet);
    }

    public function setTotalNet(Money $totalNet): void
    {
        $this->totalNet = $totalNet->getCents();
    }

    public function getTotalTax(): Money
    {
        return Money::fromCents($this->totalTax);
    }

    public function setTotalTax(Money $totalTax): void
    {
        $this->totalTax = $totalTax->getCents();
    }

    public function getTotalGross(): Money
    {
        return Money::fromCents($this->totalGross);
    }

    public function setTotalGross(Money $totalGross): void
    {
        $this->totalGross = $totalGross->getCents();
    }

    public function getTaxCountry(): string
    {
        return $this->taxCountry;
    }

    public function setTaxCountry(string $taxCountry): void
    {
        $this->taxCountry = $taxCountry;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTaxBreakdown(): array
    {
        return json_decode($this->taxBreakdown, true) ?: [];
    }

    /**
     * @param array<string, mixed> $taxBreakdown
     */
    public function setTaxBreakdown(array $taxBreakdown): void
    {
        $this->taxBreakdown = (string)json_encode($taxBreakdown);
    }

    /**
     * @return array<int, mixed>
     */
    public function getStatusLog(): array
    {
        return json_decode($this->statusLog, true) ?: [];
    }

    /**
     * @param array<int, mixed> $statusLog
     */
    public function setStatusLog(array $statusLog): void
    {
        $this->statusLog = (string)json_encode($statusLog);
    }

    public function getDiscountTotal(): Money
    {
        return Money::fromCents($this->discountTotal);
    }

    public function setDiscountTotal(Money $discountTotal): void
    {
        $this->discountTotal = $discountTotal->getCents();
    }

    /**
     * @return string[]
     */
    public function getVoucherCodes(): array
    {
        return json_decode($this->voucherCodes, true) ?: [];
    }

    /**
     * @param string[] $voucherCodes
     */
    public function setVoucherCodes(array $voucherCodes): void
    {
        $this->voucherCodes = (string)json_encode($voucherCodes);
    }

    /**
     * Which carrier shipped this order, and which of its options was used - "dhl" / "express". The
     * carrier is recorded by name rather than by reference, because it may live in an extension that is
     * no longer installed by the time anyone looks at the order.
     */
    public function getShippingProvider(): string
    {
        return $this->shippingProvider;
    }

    public function setShippingProvider(string $shippingProvider): void
    {
        $this->shippingProvider = $shippingProvider;
    }

    public function getShippingOption(): string
    {
        return $this->shippingOption;
    }

    public function setShippingOption(string $shippingOption): void
    {
        $this->shippingOption = $shippingOption;
    }

    /**
     * What the customer was shown - "DHL Express". Denormalized on purpose: the order has to keep
     * rendering after the carrier's extension is uninstalled.
     */
    public function getShippingLabel(): string
    {
        return $this->shippingLabel;
    }

    public function setShippingLabel(string $shippingLabel): void
    {
        $this->shippingLabel = $shippingLabel;
    }

    public function getShippingTotal(): Money
    {
        return Money::fromCents($this->shippingTotal);
    }

    public function setShippingTotal(Money $shippingTotal): void
    {
        $this->shippingTotal = $shippingTotal->getCents();
    }

    public function getHandlingFeeTotal(): Money
    {
        return Money::fromCents($this->handlingFeeTotal);
    }

    public function setHandlingFeeTotal(Money $handlingFeeTotal): void
    {
        $this->handlingFeeTotal = $handlingFeeTotal->getCents();
    }

    public function getDepositTotal(): Money
    {
        return Money::fromCents($this->depositTotal);
    }

    public function setDepositTotal(Money $depositTotal): void
    {
        $this->depositTotal = $depositTotal->getCents();
    }

    /**
     * @return ObjectStorage<OrderItem>
     */
    public function getItems(): ObjectStorage
    {
        return $this->items;
    }

    /**
     * @param ObjectStorage<OrderItem> $items
     */
    public function setItems(ObjectStorage $items): void
    {
        $this->items = $items;
    }

    public function getCustomerNote(): string
    {
        return $this->customerNote;
    }

    public function setCustomerNote(string $customerNote): void
    {
        $this->customerNote = $customerNote;
    }

    public function getGiftMessage(): string
    {
        return $this->giftMessage;
    }

    public function setGiftMessage(string $giftMessage): void
    {
        $this->giftMessage = $giftMessage;
    }

    public function getTermsAcceptedAt(): ?\DateTime
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?\DateTime $termsAcceptedAt): void
    {
        $this->termsAcceptedAt = $termsAcceptedAt;
    }

    public function getSiteIdentifier(): string
    {
        return $this->siteIdentifier;
    }

    public function setSiteIdentifier(string $siteIdentifier): void
    {
        $this->siteIdentifier = $siteIdentifier;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLegacyOrderData(): array
    {
        return json_decode($this->legacyOrderData, true) ?: [];
    }

    /**
     * @param array<string, mixed> $legacyOrderData
     */
    public function setLegacyOrderData(array $legacyOrderData): void
    {
        $this->legacyOrderData = (string)json_encode($legacyOrderData);
    }

    public function getLegacyCountryName(): string
    {
        return $this->legacyCountryName;
    }

    public function setLegacyCountryName(string $legacyCountryName): void
    {
        $this->legacyCountryName = $legacyCountryName;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getTermsDocument(): ObjectStorage
    {
        return $this->termsDocument;
    }

    /**
     * @param ObjectStorage<FileReference> $termsDocument
     */
    public function setTermsDocument(ObjectStorage $termsDocument): void
    {
        $this->termsDocument = $termsDocument;
    }
}
