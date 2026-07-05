<?php
declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Payment;

use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('products.payment_method')]
interface PaymentMethodInterface
{
    public function getIdentifier(): string;

    public function getTitle(): string;

    public function process(Order $order, ServerRequestInterface $request): ResponseInterface;
}
