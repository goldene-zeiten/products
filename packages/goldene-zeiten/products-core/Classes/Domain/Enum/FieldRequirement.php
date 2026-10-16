<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

/**
 * Whether an optional checkout field is offered at all, and whether the customer must fill it.
 */
enum FieldRequirement: string
{
    case Hidden = 'hidden';
    case Optional = 'optional';
    case Required = 'required';
}
