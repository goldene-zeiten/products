<?php
declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Payment;

use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class InvoicePaymentMethod implements PaymentMethodInterface
{
    public function getIdentifier(): string
    {
        return 'invoice';
    }

    public function getTitle(): string
    {
        return (string)LocalizationUtility::translate('payment_method_invoice', 'Products');
    }

    public function process(Order $order, ServerRequestInterface $request): ResponseInterface
    {
        return (new ForwardResponse('thankYou'))
            ->withArguments(['order' => $order->getUid()]);
    }
}
