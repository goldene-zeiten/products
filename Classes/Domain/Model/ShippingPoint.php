<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class ShippingPoint extends AbstractEntity
{
    protected string $title = '';
    protected string $notificationEmail = '';
    protected string $notificationRecipientName = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getNotificationEmail(): string
    {
        return $this->notificationEmail;
    }

    public function setNotificationEmail(string $notificationEmail): void
    {
        $this->notificationEmail = $notificationEmail;
    }

    public function getNotificationRecipientName(): string
    {
        return $this->notificationRecipientName;
    }

    public function setNotificationRecipientName(string $notificationRecipientName): void
    {
        $this->notificationRecipientName = $notificationRecipientName;
    }
}
