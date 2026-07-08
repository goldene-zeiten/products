<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Export;

use GoldeneZeiten\Products\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Format-agnostic order export extension point - no concrete implementation ships with this
 * extension (which format/target ERP an installation needs is entirely installation-specific);
 * a third-party extension implements this the same way a third-party payment gateway implements
 * PaymentMethodInterface, and is picked up automatically via the tagged_iterator below.
 */
#[AutoconfigureTag('products.order_export')]
interface OrderExportInterface
{
    public function getIdentifier(): string;

    public function getLabel(): string;

    public function getContentType(): string;

    public function getFileExtension(): string;

    public function export(Order $order): string;
}
