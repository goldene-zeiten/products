<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Export;

use GoldeneZeiten\Products\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contract for order exporters (ERP, DATEV, fulfillment partners, analytics). Order export is
 * shop-specific, so no concrete implementation ships with this extension - an integrator registers one
 * by implementing this interface; the autoconfigure tag collects it into {@see OrderExportRegistry}
 * without any further configuration.
 *
 * The contract covers both lifecycle phases the registry needs: discovery, where the backend asks which
 * exporters may be offered for a given order, and execution, where the exporter the editor selected
 * produces the payload.
 */
#[AutoconfigureTag('products.order_export')]
interface OrderExportInterface
{
    public function getIdentifier(): string;

    public function getLabel(): string;

    public function getContentType(): string;

    public function getFileExtension(): string;

    /**
     * Discovery phase: may this exporter be offered for the given order and backend user? An exporter
     * that only applies to shipped orders, or only to certain editors, denies here and is never offered.
     */
    public function isAvailable(ExportContext $context): bool;

    /**
     * Higher priority is offered first. Exporters sharing a priority keep their registration order.
     */
    public function getPriority(): int;

    /**
     * Execution phase: produce the payload for the order the editor selected this exporter for.
     */
    public function export(Order $order): string;
}
