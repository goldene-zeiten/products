<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Personalization\Domain\Model\Option;
use GoldeneZeiten\Products\Personalization\Domain\Model\OptionItem;

return [
    Option::class => [
        'tableName' => 'tx_productspersonalization_domain_model_option',
    ],
    OptionItem::class => [
        'tableName' => 'tx_productspersonalization_domain_model_optionitem',
    ],
];
