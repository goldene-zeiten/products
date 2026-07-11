<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * A discount code. `validFrom`/`validUntil` are this table's enablecolumns starttime/endtime (same
 * repurposing as TaxRate), so an expired or not-yet-active voucher is never returned by a normal
 * query at all rather than needing an explicit date check.
 */
#[Exclude]
class Voucher extends AbstractEntity
{
    protected string $code = '';
    protected string $title = '';
    protected VoucherDiscountType $discountType = VoucherDiscountType::PERCENTAGE;
    /** @var string */
    protected string $discountValue = '0.00';
    protected bool $combinable = false;
    protected int $usageLimit = 0;
    protected int $redemptionCount = 0;
    protected ?\DateTime $validFrom = null;
    protected ?\DateTime $validUntil = null;
    /** @var string */
    protected string $minBasketValue = '0.00';
    protected int $boundFrontendUser = 0;
    protected bool $waivesShippingCost = false;
    protected int $generatedFromOrder = 0;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDiscountType(): VoucherDiscountType
    {
        return $this->discountType;
    }

    public function setDiscountType(VoucherDiscountType $discountType): void
    {
        $this->discountType = $discountType;
    }

    public function getDiscountValue(): string
    {
        return $this->discountValue;
    }

    public function setDiscountValue(string $discountValue): void
    {
        $this->discountValue = $discountValue;
    }

    public function isCombinable(): bool
    {
        return $this->combinable;
    }

    public function setCombinable(bool $combinable): void
    {
        $this->combinable = $combinable;
    }

    public function getUsageLimit(): int
    {
        return $this->usageLimit;
    }

    public function setUsageLimit(int $usageLimit): void
    {
        $this->usageLimit = $usageLimit;
    }

    public function getRedemptionCount(): int
    {
        return $this->redemptionCount;
    }

    public function setRedemptionCount(int $redemptionCount): void
    {
        $this->redemptionCount = $redemptionCount;
    }

    public function getValidFrom(): ?\DateTime
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTime $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidUntil(): ?\DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTime $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function getMinBasketValue(): Money
    {
        return Money::fromDecimalString($this->minBasketValue);
    }

    public function setMinBasketValue(Money $minBasketValue): void
    {
        $this->minBasketValue = $minBasketValue->getDecimalString();
    }

    public function getBoundFrontendUser(): int
    {
        return $this->boundFrontendUser;
    }

    public function setBoundFrontendUser(int $boundFrontendUser): void
    {
        $this->boundFrontendUser = $boundFrontendUser;
    }

    public function isWaivingShippingCost(): bool
    {
        return $this->waivesShippingCost;
    }

    public function setWaivesShippingCost(bool $waivesShippingCost): void
    {
        $this->waivesShippingCost = $waivesShippingCost;
    }

    public function getGeneratedFromOrder(): int
    {
        return $this->generatedFromOrder;
    }

    public function setGeneratedFromOrder(int $generatedFromOrder): void
    {
        $this->generatedFromOrder = $generatedFromOrder;
    }

    public function isAvailableToFrontendUser(int $frontendUser): bool
    {
        return $this->boundFrontendUser === 0 || $this->boundFrontendUser === $frontendUser;
    }

    public function meetsMinimumBasketValue(Money $basketGoodsTotal): bool
    {
        return $basketGoodsTotal->getCents() >= $this->getMinBasketValue()->getCents();
    }

    /**
     * Never discounts more than the basket is worth, so the payable amount can't go negative.
     */
    public function calculateDiscount(Money $basketGoodsTotal): Money
    {
        $raw = $this->discountType === VoucherDiscountType::PERCENTAGE
            ? $basketGoodsTotal->multiply((float)$this->discountValue / 100)
            : Money::fromDecimalString($this->discountValue);
        return $raw->getCents() > $basketGoodsTotal->getCents() ? $basketGoodsTotal : $raw;
    }
}
