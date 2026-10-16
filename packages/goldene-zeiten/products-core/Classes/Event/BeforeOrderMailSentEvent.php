<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use TYPO3\CMS\Core\Mail\FluidEmail;

/**
 * Dispatched right before an order mail (confirmation, merchant notification, status change, withdrawal) is
 * sent. Listeners may assign further variables to the mail view, add attachments or recipients.
 *
 * The template name tells the mails apart: `OrderConfirmation`, `MerchantNotification`, `OrderStatusChanged`,
 * `Withdrawal` or `WithdrawalAcknowledgement`.
 */
final class BeforeOrderMailSentEvent
{
    public function __construct(
        private readonly FluidEmail $email,
        private readonly Order $order,
        private readonly string $template,
    ) {}

    public function getEmail(): FluidEmail
    {
        return $this->email;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }
}
