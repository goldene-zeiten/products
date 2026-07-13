<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\PriceRoundingService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

final class OrderFactory
{
    public function __construct(
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly NumberRangeService $numberRangeService,
        private readonly PriceRoundingService $priceRoundingService
    ) {}

    public function create(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        Address $address,
        string $paymentMethodIdentifier,
        PlacementDetails $details
    ): Order {
        $site = $request->getAttribute('site');
        $order = new Order();
        $order->setFrontendUser($this->frontendUserResolver->getUid($request));
        $order->setOrderDate(new \DateTime());
        $order->setTermsAcceptedAt(new \DateTime());
        $order->setSiteIdentifier($site instanceof Site ? $site->getIdentifier() : 'default');
        $order->setOrderNumber($this->generateOrderNumber($order->getSiteIdentifier(), $site));
        $order->setEmail($address->getEmail());
        $order->setPaymentMethod($paymentMethodIdentifier);
        $order->setPaymentStatus(PaymentStatus::OPEN);
        $order->setStatus(OrderStatus::PENDING);
        $order->setCurrency($basketViewModel->getCurrency());
        $order->setTotalNet($basketViewModel->getTotalNet());
        $order->setTotalTax($basketViewModel->getTotalTax());
        $this->applyAdjustments($order, $basketViewModel, $details, $site);
        $order->setTaxCountry($address->getCountry());
        $order->setBillingAddress($this->buildBillingAddress($address));
        $this->applyGiftOrderDetails($order, $details);
        $order->setItems($this->buildOrderItems($basketViewModel, $order));
        return $order;
    }

    /**
     * total_gross adjusted by discounts/shipping/fees; shipping's tax split into total_net/total_tax.
     */
    private function applyAdjustments(Order $order, BasketViewModel $basketViewModel, PlacementDetails $details, ?Site $site): void
    {
        $depositTotal = $basketViewModel->getDepositTotal();
        $adjustedTotalGross = $basketViewModel->getTotalGross()
            ->subtract($details->getTotalDiscount())
            ->add($details->getShippingCost())
            ->add($details->getHandlingFeeCost())
            ->add($depositTotal);
        $order->setTotalGross($this->priceRoundingService->round($adjustedTotalGross, $this->roundingMode($site)));
        $shippingNet = $details->getShippingCost()->netFromGross($details->getShippingTaxRate());
        $order->setTotalNet($order->getTotalNet()->add($shippingNet));
        $order->setTotalTax($order->getTotalTax()->add($details->getShippingCost()->subtract($shippingNet)));
        $order->setDiscountTotal($details->getTotalDiscount());
        $order->setVoucherCodes($details->getVoucherCodes());
        $order->setShippingMethod($details->getShippingMethodUid());
        $order->setShippingTotal($details->getShippingCost());
        $order->setHandlingFeeTotal($details->getHandlingFeeCost());
        $order->setDepositTotal($depositTotal);
    }

    private function applyGiftOrderDetails(Order $order, PlacementDetails $details): void
    {
        if ($details->getDeliveryAddress() !== null) {
            $order->setDeliveryAddress($this->buildDeliveryAddress($details->getDeliveryAddress()));
        }
        $order->setGiftMessage($details->getGiftMessage());
    }

    private function roundingMode(?Site $site): string
    {
        return (string)($site?->getSettings()->get('products.pricing.roundingMode', PriceRoundingService::MODE_NONE) ?? PriceRoundingService::MODE_NONE);
    }

    private function generateOrderNumber(string $siteIdentifier, ?Site $site): string
    {
        $scope = sprintf('order:%s:%s', $siteIdentifier, (new \DateTimeImmutable())->format('Y'));
        $prefix = (string)($site?->getSettings()->get('products.order.numberPrefix', 'ORD-') ?? 'ORD-');
        return $prefix . $this->numberRangeService->next($scope);
    }

    private function buildBillingAddress(Address $address): OrderAddress
    {
        return $this->buildOrderAddress($address, 'billing');
    }

    private function buildDeliveryAddress(Address $address): OrderAddress
    {
        return $this->buildOrderAddress($address, 'delivery');
    }

    private function buildOrderAddress(Address $address, string $addressType): OrderAddress
    {
        $orderAddress = new OrderAddress();
        $orderAddress->setAddressType($addressType);
        $orderAddress->setSalutation($address->getSalutation());
        $orderAddress->setFirstName($address->getFirstName());
        $orderAddress->setLastName($address->getLastName());
        $orderAddress->setCompany($address->getCompany());
        $orderAddress->setStreet($address->getStreet());
        $orderAddress->setZip($address->getZip());
        $orderAddress->setCity($address->getCity());
        $orderAddress->setCountry($address->getCountry());
        return $orderAddress;
    }

    /**
     * @return ObjectStorage<OrderItem>
     */
    private function buildOrderItems(BasketViewModel $basketViewModel, Order $order): ObjectStorage
    {
        /** @var ObjectStorage<OrderItem> $orderItems */
        $orderItems = new ObjectStorage();
        foreach ($basketViewModel->getItems() as $viewItem) {
            $orderItems->attach($this->buildOrderItem($viewItem, $order));
        }
        return $orderItems;
    }

    private function buildOrderItem(BasketViewItem $viewItem, Order $order): OrderItem
    {
        $orderItem = new OrderItem();
        $orderItem->setProduct($viewItem->getProduct()->getUid() ?? 0);
        if ($viewItem->getArticle() !== null) {
            $orderItem->setArticle($viewItem->getArticle()->getUid() ?? 0);
            $orderItem->setArticleTitle($viewItem->getArticle()->getTitle());
        }
        $orderItem->setTitle($viewItem->getProduct()->getTitle());
        $itemNumber = $viewItem->getArticle()?->getItemNumber() ?? $viewItem->getProduct()->getItemNumber();
        $orderItem->setItemNumber($itemNumber);
        $orderItem->setQuantity($viewItem->getQuantity());
        $orderItem->setUnitPriceNet($viewItem->getUnitPriceNet());
        $orderItem->setUnitPriceGross($viewItem->getUnitPriceGross());
        $orderItem->setTaxRate($viewItem->getTaxRate());
        $orderItem->setLineTotalNet($viewItem->getLineTotalNet());
        $orderItem->setLineTotalTax($viewItem->getLineTotalTax());
        $orderItem->setLineTotalGross($viewItem->getLineTotalGross());
        $orderItem->setDepositTotal($viewItem->getDepositTotal());
        $orderItem->setParentOrder($order);
        return $orderItem;
    }
}
