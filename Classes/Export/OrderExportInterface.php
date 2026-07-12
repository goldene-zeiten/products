<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Export;

use GoldeneZeiten\Products\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point for order export; no concrete implementation ships with this extension.
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
