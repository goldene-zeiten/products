<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Page;

/**
 * The doktypes products-core registers on the pages table.
 */
final class PageTypes
{
    /**
     * The dedicated withdrawal page (Art. 11a Directive 2011/83/EU): renders the withdrawal form
     * as its content and is meant to be linked from a footer/meta navigation, styleable per
     * navigation item via the pages field `tx_products_nav_class`.
     */
    public const DOKTYPE_WITHDRAWAL = 175;

    private function __construct() {}
}
