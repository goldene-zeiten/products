<?php
declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use GoldeneZeiten\Products\Domain\Model\Order;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        $email = new FluidEmail();
        $email
            ->to($order->getEmail())
            ->subject((string)LocalizationUtility::translate('order_confirmation_subject', 'Products', [$order->getOrderNumber()]))
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate('OrderConfirmation')
            ->assign('order', $order);

        $this->mailer->send($email);
    }
}
