<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Attribute;
use GoldeneZeiten\Products\Core\Domain\Model\AttributeValue;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\CreditPointsTransaction;
use GoldeneZeiten\Products\Core\Domain\Model\HandlingFee;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Core\Domain\Model\PriceHistoryEntry;
use GoldeneZeiten\Products\Core\Domain\Model\PricePeriod;
use GoldeneZeiten\Products\Core\Domain\Model\PriceTier;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Model\ShippingMethod;
use GoldeneZeiten\Products\Core\Domain\Model\ShippingPoint;
use GoldeneZeiten\Products\Core\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Core\Domain\Model\TaxRate;
use GoldeneZeiten\Products\Core\Domain\Model\Voucher;
use GoldeneZeiten\Products\Core\Domain\Model\VoucherRedemption;

// The core extension key is `products_core`, so Extbase's default table-name
// convention would resolve to `tx_products_core_domain_model_*`. The tables keep
// their original `tx_products_*` names (they are not derived from the key and
// renaming them carries no benefit here), so each aggregate root is mapped to its
// existing table explicitly.
return [
    Article::class => ['tableName' => 'tx_products_domain_model_article'],
    Attribute::class => ['tableName' => 'tx_products_domain_model_attribute'],
    AttributeValue::class => ['tableName' => 'tx_products_domain_model_attributevalue'],
    Category::class => ['tableName' => 'tx_products_domain_model_category'],
    CreditPointsTransaction::class => ['tableName' => 'tx_products_domain_model_creditpointstransaction'],
    HandlingFee::class => ['tableName' => 'tx_products_domain_model_handlingfee'],
    Order::class => ['tableName' => 'tx_products_domain_model_order'],
    OrderAddress::class => ['tableName' => 'tx_products_domain_model_orderaddress'],
    OrderItem::class => ['tableName' => 'tx_products_domain_model_orderitem'],
    PaymentTransaction::class => ['tableName' => 'tx_products_domain_model_paymenttransaction'],
    PriceHistoryEntry::class => ['tableName' => 'tx_products_domain_model_pricehistoryentry'],
    PricePeriod::class => ['tableName' => 'tx_products_domain_model_priceperiod'],
    PriceTier::class => ['tableName' => 'tx_products_domain_model_pricetier'],
    Product::class => ['tableName' => 'tx_products_domain_model_product'],
    ShippingMethod::class => ['tableName' => 'tx_products_domain_model_shippingmethod'],
    ShippingPoint::class => ['tableName' => 'tx_products_domain_model_shippingpoint'],
    TaxClass::class => ['tableName' => 'tx_products_domain_model_taxclass'],
    TaxRate::class => ['tableName' => 'tx_products_domain_model_taxrate'],
    Voucher::class => ['tableName' => 'tx_products_domain_model_voucher'],
    VoucherRedemption::class => ['tableName' => 'tx_products_domain_model_voucherredemption'],
];
