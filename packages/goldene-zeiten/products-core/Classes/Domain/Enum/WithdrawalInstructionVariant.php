<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

/**
 * Which start of the withdrawal period the model instructions state (Annex I(A), instruction 1 of
 * Directive 2011/83/EU): the receipt of the goods in its single, multiple, partial-delivery and
 * regular-delivery forms, or the conclusion of the contract for digital content.
 */
enum WithdrawalInstructionVariant: string
{
    case GOODS = 'goods';
    case MULTIPLE_GOODS = 'multipleGoods';
    case LOTS = 'lots';
    case REGULAR_DELIVERY = 'regularDelivery';
    case DIGITAL_CONTENT = 'digitalContent';

    public function isGoods(): bool
    {
        return $this !== self::DIGITAL_CONTENT;
    }
}
