<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class PaymentTransaction extends AbstractEntity
{
    protected int $orderUid = 0;
    protected string $paymentMethod = '';
    protected string $externalId = '';
    protected string $state = '';
    protected int $amount = 0;
    protected string $currency = '';
    protected string $rawData = '[]';

    public function getOrderUid(): int
    {
        return $this->orderUid;
    }

    public function setOrderUid(int $orderUid): void
    {
        $this->orderUid = $orderUid;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return json_decode($this->rawData, true) ?: [];
    }

    /**
     * @param array<string, mixed> $rawData
     */
    public function setRawData(array $rawData): void
    {
        $this->rawData = (string)json_encode($rawData);
    }
}
