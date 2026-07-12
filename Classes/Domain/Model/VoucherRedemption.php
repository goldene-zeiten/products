<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class VoucherRedemption extends AbstractEntity
{
    protected int $voucherUid = 0;
    protected string $voucherCode = '';
    protected int $orderUid = 0;
    protected int $frontendUser = 0;
    protected int $discountTotal = 0;
    protected ?\DateTime $redeemedAt = null;

    public function getVoucherUid(): int
    {
        return $this->voucherUid;
    }

    public function setVoucherUid(int $voucherUid): void
    {
        $this->voucherUid = $voucherUid;
    }

    public function getVoucherCode(): string
    {
        return $this->voucherCode;
    }

    public function setVoucherCode(string $voucherCode): void
    {
        $this->voucherCode = $voucherCode;
    }

    public function getOrderUid(): int
    {
        return $this->orderUid;
    }

    public function setOrderUid(int $orderUid): void
    {
        $this->orderUid = $orderUid;
    }

    public function getFrontendUser(): int
    {
        return $this->frontendUser;
    }

    public function setFrontendUser(int $frontendUser): void
    {
        $this->frontendUser = $frontendUser;
    }

    public function getDiscountTotal(): int
    {
        return $this->discountTotal;
    }

    public function setDiscountTotal(int $discountTotal): void
    {
        $this->discountTotal = $discountTotal;
    }

    public function getRedeemedAt(): ?\DateTime
    {
        return $this->redeemedAt;
    }

    public function setRedeemedAt(?\DateTime $redeemedAt): void
    {
        $this->redeemedAt = $redeemedAt;
    }
}
