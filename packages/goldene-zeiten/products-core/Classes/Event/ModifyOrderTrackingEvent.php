<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Controller\OrderController;
use GoldeneZeiten\Products\Core\Domain\Dto\OrderTrackingLink;
use GoldeneZeiten\Products\Core\Domain\Model\Order;

/**
 * Lets shipping/fulfilment extensions attach tracking links to an order as its detail page
 * renders. A pluggable collection so several extensions can each contribute links — a
 * parcel-tracking URL per carrier, a returns portal, a delivery-status page.
 *
 * Add entries with {@see ModifyOrderTrackingEvent::addTrackingLink()}.
 *
 * @see OrderController::showAction()
 */
final class ModifyOrderTrackingEvent
{
    /**
     * @var OrderTrackingLink[]
     */
    private array $trackingLinks = [];

    public function __construct(
        private readonly Order $order
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function addTrackingLink(OrderTrackingLink $trackingLink): void
    {
        $this->trackingLinks[] = $trackingLink;
    }

    /**
     * @return OrderTrackingLink[]
     */
    public function getTrackingLinks(): array
    {
        return $this->trackingLinks;
    }
}
