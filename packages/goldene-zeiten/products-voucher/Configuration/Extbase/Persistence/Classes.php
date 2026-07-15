<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use GoldeneZeiten\Products\Voucher\Domain\Model\VoucherRedemption;

return [
    Voucher::class => [
        'tableName' => 'tx_products_domain_model_voucher',
    ],
    VoucherRedemption::class => [
        'tableName' => 'tx_products_domain_model_voucherredemption',
    ],
];
