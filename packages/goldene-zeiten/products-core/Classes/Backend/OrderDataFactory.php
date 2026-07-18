<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderAddressData;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderItemData;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds the {@see OrderData} aggregate - the order row, its billing and delivery address and its line
 * items - for a given order uid, for the backend export and refund seams.
 */
final class OrderDataFactory
{
    private const TABLE_ORDER = 'tx_products_domain_model_order';
    private const TABLE_ORDERITEM = 'tx_products_domain_model_orderitem';
    private const TABLE_ORDERADDRESS = 'tx_products_domain_model_orderaddress';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    public function build(int $uid): ?OrderData
    {
        $queryBuilder = $this->queryBuilderFor(self::TABLE_ORDER);
        $row = $queryBuilder->select('*')
            ->from(self::TABLE_ORDER)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return null;
        }

        return new OrderData(
            uid: (int)$row['uid'],
            orderNumber: (string)$row['order_number'],
            orderDate: $this->dateFromTimestamp((int)($row['order_date'] ?? 0)),
            email: (string)$row['email'],
            billingAddress: $this->buildAddress((int)($row['billing_address'] ?? 0)),
            deliveryAddress: $this->buildAddress((int)($row['delivery_address'] ?? 0)),
            paymentMethod: (string)$row['payment_method'],
            paymentStatus: PaymentStatus::from((string)$row['payment_status']),
            status: OrderStatus::from((string)$row['status']),
            invoiceNumber: (string)$row['invoice_number'],
            currency: (string)$row['currency'],
            totalNet: Money::fromCents((int)$row['total_net']),
            totalTax: Money::fromCents((int)$row['total_tax']),
            totalGross: Money::fromCents((int)$row['total_gross']),
            discountTotal: Money::fromCents((int)($row['discount_total'] ?? 0)),
            shippingTotal: Money::fromCents((int)($row['shipping_total'] ?? 0)),
            handlingFeeTotal: Money::fromCents((int)($row['handling_fee_total'] ?? 0)),
            depositTotal: Money::fromCents((int)($row['deposit_total'] ?? 0)),
            shippingProvider: (string)($row['shipping_provider'] ?? ''),
            shippingOption: (string)($row['shipping_option'] ?? ''),
            shippingLabel: (string)($row['shipping_label'] ?? ''),
            taxCountry: (string)($row['tax_country'] ?? ''),
            taxBreakdown: $this->decodeJsonMap((string)($row['tax_breakdown'] ?? '[]')),
            statusLog: $this->decodeJsonList((string)($row['status_log'] ?? '[]')),
            customerNote: (string)($row['customer_note'] ?? ''),
            giftMessage: (string)($row['gift_message'] ?? ''),
            siteIdentifier: (string)($row['site_identifier'] ?? ''),
            items: $this->buildItems($uid),
        );
    }

    private function buildAddress(int $uid): ?OrderAddressData
    {
        if ($uid <= 0) {
            return null;
        }
        $queryBuilder = $this->queryBuilderFor(self::TABLE_ORDERADDRESS);
        $row = $queryBuilder->select('*')
            ->from(self::TABLE_ORDERADDRESS)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return null;
        }

        return new OrderAddressData(
            addressType: (string)($row['address_type'] ?? ''),
            company: (string)($row['company'] ?? ''),
            salutation: (string)($row['salutation'] ?? ''),
            firstName: (string)($row['first_name'] ?? ''),
            lastName: (string)($row['last_name'] ?? ''),
            street: (string)($row['street'] ?? ''),
            houseNumber: (string)($row['house_number'] ?? ''),
            zip: (string)($row['zip'] ?? ''),
            city: (string)($row['city'] ?? ''),
            country: (string)($row['country'] ?? ''),
            telephone: (string)($row['telephone'] ?? ''),
            vatId: (string)($row['vat_id'] ?? ''),
        );
    }

    /**
     * @return array<int, OrderItemData>
     */
    private function buildItems(int $orderUid): array
    {
        $queryBuilder = $this->queryBuilderFor(self::TABLE_ORDERITEM);
        $result = $queryBuilder->select('*')
            ->from(self::TABLE_ORDERITEM)
            ->where($queryBuilder->expr()->eq('parent_order', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->orderBy('uid', 'ASC')
            ->executeQuery();

        $items = [];
        while ($row = $result->fetchAssociative()) {
            $items[] = new OrderItemData(
                uid: (int)$row['uid'],
                product: (int)($row['product'] ?? 0),
                article: (int)($row['article'] ?? 0),
                title: (string)($row['title'] ?? ''),
                articleTitle: (string)($row['article_title'] ?? ''),
                itemNumber: (string)($row['item_number'] ?? ''),
                quantity: (int)($row['quantity'] ?? 0),
                unitPriceNet: Money::fromCents((int)($row['unit_price_net'] ?? 0)),
                unitPriceGross: Money::fromCents((int)($row['unit_price_gross'] ?? 0)),
                taxRate: (float)($row['tax_rate'] ?? 0.0),
                lineTotalNet: Money::fromCents((int)($row['line_total_net'] ?? 0)),
                lineTotalTax: Money::fromCents((int)($row['line_total_tax'] ?? 0)),
                lineTotalGross: Money::fromCents((int)($row['line_total_gross'] ?? 0)),
                depositTotal: Money::fromCents((int)($row['deposit_total'] ?? 0)),
                options: $this->decodeJsonMap((string)($row['options'] ?? '[]')),
            );
        }

        return $items;
    }

    private function queryBuilderFor(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder;
    }

    private function dateFromTimestamp(int $timestamp): ?\DateTimeImmutable
    {
        return $timestamp > 0 ? (new \DateTimeImmutable())->setTimestamp($timestamp) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonMap(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonList(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }
}
