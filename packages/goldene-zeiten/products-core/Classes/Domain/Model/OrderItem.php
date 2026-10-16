<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class OrderItem extends AbstractEntity
{
    protected ?Order $parentOrder = null;
    protected int $product = 0;
    protected int $article = 0;
    protected string $title = '';
    protected string $articleTitle = '';
    protected string $itemNumber = '';
    protected int $quantity = 0;
    /** @var int */
    protected int $unitPriceNet = 0;
    /** @var int */
    protected int $unitPriceGross = 0;
    protected float $taxRate = 0.0;
    /** @var int */
    protected int $lineTotalNet = 0;
    /** @var int */
    protected int $lineTotalTax = 0;
    /** @var int */
    protected int $lineTotalGross = 0;
    /** @var int */
    protected int $depositTotal = 0;
    /** @var string */
    protected string $options = '[]';
    protected WithdrawalExclusion $withdrawalExclusion = WithdrawalExclusion::NONE;
    protected ?\DateTime $deliveredAt = null;
    protected ?\DateTime $withdrawnAt = null;
    protected ?\DateTime $restockedAt = null;

    public function getParentOrder(): ?Order
    {
        return $this->parentOrder;
    }

    public function setParentOrder(?Order $parentOrder): void
    {
        $this->parentOrder = $parentOrder;
    }

    public function getProduct(): int
    {
        return $this->product;
    }

    public function setProduct(int $product): void
    {
        $this->product = $product;
    }

    public function getArticle(): int
    {
        return $this->article;
    }

    public function setArticle(int $article): void
    {
        $this->article = $article;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getArticleTitle(): string
    {
        return $this->articleTitle;
    }

    public function setArticleTitle(string $articleTitle): void
    {
        $this->articleTitle = $articleTitle;
    }

    public function getItemNumber(): string
    {
        return $this->itemNumber;
    }

    public function setItemNumber(string $itemNumber): void
    {
        $this->itemNumber = $itemNumber;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPriceNet(): Money
    {
        return Money::fromCents($this->unitPriceNet);
    }

    public function setUnitPriceNet(Money $unitPriceNet): void
    {
        $this->unitPriceNet = $unitPriceNet->getCents();
    }

    public function getUnitPriceGross(): Money
    {
        return Money::fromCents($this->unitPriceGross);
    }

    public function setUnitPriceGross(Money $unitPriceGross): void
    {
        $this->unitPriceGross = $unitPriceGross->getCents();
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getLineTotalNet(): Money
    {
        return Money::fromCents($this->lineTotalNet);
    }

    public function setLineTotalNet(Money $lineTotalNet): void
    {
        $this->lineTotalNet = $lineTotalNet->getCents();
    }

    public function getLineTotalTax(): Money
    {
        return Money::fromCents($this->lineTotalTax);
    }

    public function setLineTotalTax(Money $lineTotalTax): void
    {
        $this->lineTotalTax = $lineTotalTax->getCents();
    }

    public function getLineTotalGross(): Money
    {
        return Money::fromCents($this->lineTotalGross);
    }

    public function setLineTotalGross(Money $lineTotalGross): void
    {
        $this->lineTotalGross = $lineTotalGross->getCents();
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
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return json_decode($this->options, true) ?: [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = (string)json_encode($options);
    }

    /**
     * The options the customer chose for this line, as they were sold.
     *
     * @return LineItemOption[]
     */
    public function getLineOptions(): array
    {
        $lineOptions = $this->getOptions()['lineOptions'] ?? [];
        return is_array($lineOptions) ? array_map(LineItemOption::fromArray(...), array_values(array_filter($lineOptions, 'is_array'))) : [];
    }

    /**
     * @param LineItemOption[] $lineOptions
     */
    public function setLineOptions(array $lineOptions): void
    {
        $options = $this->getOptions();
        unset($options['lineOptions']);
        if ($lineOptions !== []) {
            $options['lineOptions'] = array_map(static fn(LineItemOption $option): array => $option->toArray(), $lineOptions);
        }
        $this->setOptions($options);
    }

    /**
     * Snapshotted from the product at placement time - like the title, the legal status the
     * item was sold under must survive later product edits.
     */
    public function getWithdrawalExclusion(): WithdrawalExclusion
    {
        return $this->withdrawalExclusion;
    }

    public function setWithdrawalExclusion(WithdrawalExclusion $withdrawalExclusion): void
    {
        $this->withdrawalExclusion = $withdrawalExclusion;
    }

    /**
     * Set when this item reached the customer in a delivery of its own; the withdrawal period of an
     * order delivered in several parts runs from the last of them (Art. 9(2)(b)(ii)).
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
     * When the customer withdrew from the contract for this item; null while the item stands.
     */
    public function getWithdrawnAt(): ?\DateTime
    {
        return $this->withdrawnAt;
    }

    public function setWithdrawnAt(?\DateTime $withdrawnAt): void
    {
        $this->withdrawnAt = $withdrawnAt;
    }

    /**
     * When the item was put back into stock after it was withdrawn.
     */
    public function getRestockedAt(): ?\DateTime
    {
        return $this->restockedAt;
    }

    public function setRestockedAt(?\DateTime $restockedAt): void
    {
        $this->restockedAt = $restockedAt;
    }
}
