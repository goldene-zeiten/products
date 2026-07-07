<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\Enum\CreditPointsTransactionType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * One entry in a frontend user's points ledger. The balance is always derived by summing this
 * table rather than stored on a mutable column, avoiding a race condition under concurrent
 * checkouts (same reasoning as VoucherRedemption).
 */
#[Exclude]
class CreditPointsTransaction extends AbstractEntity
{
    protected int $frontendUser = 0;
    protected int $orderUid = 0;
    protected int $points = 0;
    protected CreditPointsTransactionType $type = CreditPointsTransactionType::EARN;
    protected ?\DateTime $created = null;

    public function getFrontendUser(): int
    {
        return $this->frontendUser;
    }

    public function setFrontendUser(int $frontendUser): void
    {
        $this->frontendUser = $frontendUser;
    }

    public function getOrderUid(): int
    {
        return $this->orderUid;
    }

    public function setOrderUid(int $orderUid): void
    {
        $this->orderUid = $orderUid;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getType(): CreditPointsTransactionType
    {
        return $this->type;
    }

    public function setType(CreditPointsTransactionType $type): void
    {
        $this->type = $type;
    }

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function setCreated(?\DateTime $created): void
    {
        $this->created = $created;
    }
}
