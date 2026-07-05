<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

final class OrderFactory
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly NumberRangeService $numberRangeService,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function create(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        Address $address,
        string $paymentMethodIdentifier
    ): Order {
        $order = new Order();
        $order->setFrontendUser($this->frontendUserResolver->getUid($request));
        $order->setOrderDate(new \DateTime());
        $order->setSiteIdentifier($this->resolveSiteIdentifier($request));
        $order->setOrderNumber($this->generateOrderNumber($order->getSiteIdentifier()));
        $order->setEmail($address->getEmail());
        $order->setPaymentMethod($paymentMethodIdentifier);
        $order->setPaymentStatus(PaymentStatus::OPEN);
        $order->setStatus(OrderStatus::PENDING);
        $order->setCurrency($basketViewModel->getCurrency());
        $order->setTotalNet($basketViewModel->getTotalNet());
        $order->setTotalTax($basketViewModel->getTotalTax());
        $order->setTotalGross($basketViewModel->getTotalGross());
        $order->setTaxCountry($address->getCountry());
        $order->setBillingAddress($this->buildBillingAddress($address));
        $order->setItems($this->buildOrderItems($basketViewModel, $order));
        return $order;
    }

    private function resolveSiteIdentifier(ServerRequestInterface $request): string
    {
        $site = $request->getAttribute('site');
        return $site instanceof SiteInterface ? $site->getIdentifier() : 'default';
    }

    private function generateOrderNumber(string $siteIdentifier): string
    {
        $scope = sprintf('order:%s:%s', $siteIdentifier, (new \DateTimeImmutable())->format('Y'));
        $prefix = (string)($this->settings['order']['numberPrefix'] ?? 'ORD-');
        return $prefix . $this->numberRangeService->next($scope);
    }

    private function buildBillingAddress(Address $address): OrderAddress
    {
        $billingAddress = new OrderAddress();
        $billingAddress->setAddressType('billing');
        $billingAddress->setSalutation($address->getSalutation());
        $billingAddress->setFirstName($address->getFirstName());
        $billingAddress->setLastName($address->getLastName());
        $billingAddress->setCompany($address->getCompany());
        $billingAddress->setStreet($address->getStreet());
        $billingAddress->setZip($address->getZip());
        $billingAddress->setCity($address->getCity());
        $billingAddress->setCountry($address->getCountry());
        return $billingAddress;
    }

    /**
     * @return ObjectStorage<OrderItem>
     */
    private function buildOrderItems(BasketViewModel $basketViewModel, Order $order): ObjectStorage
    {
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
        $orderItem->setParentOrder($order);
        return $orderItem;
    }
}
