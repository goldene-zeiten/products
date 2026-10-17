<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\VatLine;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
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
    protected string $shippingProvider = '';
    protected string $shippingOption = '';
    protected string $shippingLabel = '';
    /** @var int */
    protected int $shippingTotal = 0;
    /** @var int */
    protected int $handlingFeeTotal = 0;
    /** @var int */
    protected int $depositTotal = 0;
    /** @var int */
    protected int $paymentFeeTotal = 0;
    /**
     * @var ObjectStorage<OrderItem>
     */
    protected ObjectStorage $items;
    protected string $customerNote = '';
    protected string $giftMessage = '';
    protected ?\DateTime $desiredDeliveryDate = null;
    protected ?\DateTime $termsAcceptedAt = null;
    protected ?\DateTime $withdrawalWaiverAcceptedAt = null;
    protected ?\DateTime $deliveredAt = null;
    protected ?\DateTime $withdrawalInformationProvidedAt = null;
    protected ?\DateTime $withdrawnAt = null;
    protected ?\DateTime $returnProofAt = null;
    protected ?\DateTime $returnedAt = null;
    protected string $withdrawalLog = '[]';
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $termsDocument;
    protected string $siteIdentifier = '';
    protected int $languageId = 0;
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

    /**
     * The name on the billing address, the prefill for the name field of the withdrawal statement.
     */
    public function getBillingName(): string
    {
        $address = $this->billingAddress;
        return $address === null ? '' : trim($address->getFirstName() . ' ' . $address->getLastName());
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
     * VAT cents per rate percentage, highest rate first, e.g. ["19" => 320, "7" => 70].
     *
     * @return array<int|string, mixed>
     */
    public function getTaxBreakdown(): array
    {
        return json_decode($this->taxBreakdown, true) ?: [];
    }

    /**
     * @param array<int|string, mixed> $taxBreakdown
     */
    public function setTaxBreakdown(array $taxBreakdown): void
    {
        $this->taxBreakdown = (string)json_encode($taxBreakdown);
    }

    /**
     * @return VatLine[]
     */
    public function getVatLines(): array
    {
        $lines = [];
        foreach ($this->getTaxBreakdown() as $percent => $cents) {
            $lines[] = new VatLine((string)$percent, Money::fromCents((int)$cents));
        }
        return $lines;
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

    public function getPaymentFeeTotal(): Money
    {
        return Money::fromCents($this->paymentFeeTotal);
    }

    public function setPaymentFeeTotal(Money $paymentFeeTotal): void
    {
        $this->paymentFeeTotal = $paymentFeeTotal->getCents();
    }

    /**
     * The gross of all order lines, before any charge or reduction on top.
     */
    public function getItemsTotalGross(): Money
    {
        $total = Money::fromCents(0);
        foreach ($this->items as $item) {
            $total = $total->add($item->getLineTotalGross());
        }
        return $total;
    }

    /**
     * What the shop's rounding mode moved the total by: whatever the lines, charges and reductions do not
     * account for.
     */
    public function getRoundingDifference(): Money
    {
        return $this->getTotalGross()
            ->subtract($this->getItemsTotalGross())
            ->subtract($this->getShippingTotal())
            ->subtract($this->getHandlingFeeTotal())
            ->subtract($this->getDepositTotal())
            ->subtract($this->getPaymentFeeTotal())
            ->add($this->getDiscountTotal());
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

    public function getDesiredDeliveryDate(): ?\DateTime
    {
        return $this->desiredDeliveryDate;
    }

    public function setDesiredDeliveryDate(?\DateTime $desiredDeliveryDate): void
    {
        $this->desiredDeliveryDate = $desiredDeliveryDate;
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

    /**
     * When the customer expressly consented to immediate supply of digital content and
     * acknowledged losing the withdrawal right for it (Art. 16(m) Directive 2011/83/EU).
     * Null on orders without digital items - or placed before this consent existed.
     */
    public function getWithdrawalWaiverAcceptedAt(): ?\DateTime
    {
        return $this->withdrawalWaiverAcceptedAt;
    }

    public function setWithdrawalWaiverAcceptedAt(?\DateTime $withdrawalWaiverAcceptedAt): void
    {
        $this->withdrawalWaiverAcceptedAt = $withdrawalWaiverAcceptedAt;
    }

    /**
     * When the customer, or a third party they named, took physical possession of the last good
     * (Art. 9(2)(b) Directive 2011/83/EU). Set when the backend marks the order delivered, or by an
     * integration that learns of the delivery; null while it is unknown or still under way.
     */
    public function getDeliveredAt(): ?\DateTime
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTime $deliveredAt): void
    {
        $this->deliveredAt = $deliveredAt;
    }

    /**
     * When the withdrawal information (Annex I(A) instructions) reached the customer on a durable
     * medium. Without it the withdrawal period is extended by twelve months (Art. 10).
     */
    public function getWithdrawalInformationProvidedAt(): ?\DateTime
    {
        return $this->withdrawalInformationProvidedAt;
    }

    public function setWithdrawalInformationProvidedAt(?\DateTime $withdrawalInformationProvidedAt): void
    {
        $this->withdrawalInformationProvidedAt = $withdrawalInformationProvidedAt;
    }

    /**
     * Submission time of the latest withdrawal statement - the moment that decides whether the
     * withdrawal was timely (Art. 11a(5)) and from which the refund period runs (Art. 13).
     */
    public function getWithdrawnAt(): ?\DateTime
    {
        return $this->withdrawnAt;
    }

    public function setWithdrawnAt(?\DateTime $withdrawnAt): void
    {
        $this->withdrawnAt = $withdrawnAt;
    }

    public function getReturnProofAt(): ?\DateTime
    {
        return $this->returnProofAt;
    }

    public function setReturnProofAt(?\DateTime $returnProofAt): void
    {
        $this->returnProofAt = $returnProofAt;
    }

    public function getReturnedAt(): ?\DateTime
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?\DateTime $returnedAt): void
    {
        $this->returnedAt = $returnedAt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWithdrawalLog(): array
    {
        return json_decode($this->withdrawalLog, true) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $withdrawalLog
     */
    public function setWithdrawalLog(array $withdrawalLog): void
    {
        $this->withdrawalLog = (string)json_encode($withdrawalLog);
    }

    /**
     * When the order was handed to the carrier, read from the status log; null if it never was.
     */
    public function getShippedAt(): ?\DateTimeImmutable
    {
        foreach (array_reverse($this->getStatusLog()) as $entry) {
            if (($entry['to'] ?? '') === OrderStatus::SHIPPED->value && isset($entry['at'])) {
                return new \DateTimeImmutable((string)$entry['at']);
            }
        }
        return null;
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
     * The site language the order was placed in.
     */
    public function getLanguageId(): int
    {
        return $this->languageId;
    }

    public function setLanguageId(int $languageId): void
    {
        $this->languageId = $languageId;
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
