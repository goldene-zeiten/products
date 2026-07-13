<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

/**
 * Maps a legacy tt_products `display_mode` to the CType (plus TCA field values) that replaces it.
 * A mode absent from these tables has no equivalent in this extension - gifts, the DAM and
 * address-book families, the USER1-USER5 hook slots, dev-only modes - and is deliberately not
 * migrated; see {@see TtProductsPluginUpgradeWizard}, which reports such elements instead.
 */
final class LegacyPluginModeMap
{
    /**
     * Modes of list_type 5 and tt_products_pi_int.
     *
     * @var array<string, array{ctype: string, fields: array<string, string>}>
     */
    private const MODE_TARGETS = [
        'LIST' => ['ctype' => 'products_productlist', 'fields' => ['tx_products_list_mode' => 'all']],
        'LISTOFFERS' => ['ctype' => 'products_productlist', 'fields' => ['tx_products_list_mode' => 'offers']],
        'LISTHIGHLIGHTS' => ['ctype' => 'products_productlist', 'fields' => ['tx_products_list_mode' => 'highlights']],
        'LISTNEWITEMS' => ['ctype' => 'products_productlist', 'fields' => ['tx_products_list_mode' => 'new']],
        'LISTAFFORDABLE' => ['ctype' => 'products_productlist', 'fields' => ['tx_products_list_mode' => 'affordable']],
        'LISTARTICLES' => ['ctype' => 'products_productlist', 'fields' => ['tx_products_list_mode' => 'articles']],
        'LISTVIEWEDITEMS' => ['ctype' => 'products_recentlyviewed', 'fields' => ['tx_products_recentlyviewed_mode' => 'recent']],
        'LISTVIEWEDMOST' => ['ctype' => 'products_recentlyviewed', 'fields' => ['tx_products_recentlyviewed_mode' => 'mostviewed']],
        'LISTVIEWEDMOSTOTHERS' => ['ctype' => 'products_recentlyviewed', 'fields' => ['tx_products_recentlyviewed_mode' => 'mostviewedglobal']],
        'SINGLE' => ['ctype' => 'products_productdetail', 'fields' => []],
        'SEARCH' => ['ctype' => 'products_search', 'fields' => ['tx_products_search_browse_mode' => 'text']],
        'MEMO' => ['ctype' => 'products_wishlist', 'fields' => []],
        'BASKET' => ['ctype' => 'products_basket', 'fields' => []],
        'ORDERS' => ['ctype' => 'products_orderhistory', 'fields' => []],
        'BILL' => ['ctype' => 'products_invoice', 'fields' => []],
        'WITHDRAWAL' => ['ctype' => 'products_withdrawal', 'fields' => []],
        'DOWNLOAD' => ['ctype' => 'products_download', 'fields' => []],
        'LISTCAT' => ['ctype' => 'products_categorylist', 'fields' => []],
        'SELECTCAT' => ['ctype' => 'products_categorynavigation', 'fields' => ['tx_products_navigation_style' => 'dropdown']],
        'MENUCAT' => ['ctype' => 'products_categorynavigation', 'fields' => ['tx_products_navigation_style' => 'menu']],
        'INFO' => ['ctype' => 'products_checkout', 'fields' => []],
        'PAYMENT' => ['ctype' => 'products_checkout', 'fields' => []],
        'FINALIZE' => ['ctype' => 'products_checkout', 'fields' => []],
        'DELIVERY' => ['ctype' => 'products_checkout', 'fields' => []],
        'TRACKING' => ['ctype' => 'products_checkout', 'fields' => []],
        'OVERVIEW' => ['ctype' => 'products_checkout', 'fields' => []],
    ];

    /**
     * tt_products_pi_search has its own display_mode vocabulary. KEYFIELD (browse by a keyword
     * column) has no dedicated equivalent and is approximated by the generic field browse.
     *
     * @var array<string, string>
     */
    private const SEARCH_MODE_TARGETS = [
        'TEXTFIELD' => 'text',
        'FIRSTLETTER' => 'firstletter',
        'YEAR' => 'year',
        'LASTENTRIES' => 'lastentries',
        'FIELD' => 'field',
        'KEYFIELD' => 'field',
    ];

    private const APPROXIMATED_SEARCH_MODE = 'KEYFIELD';

    private const DEFAULT_SEARCH_MODE = 'text';

    /**
     * @return array{ctype: string, fields: array<string, string>}|null null when the mode has no equivalent
     */
    public function resolveMode(string $mode): ?array
    {
        return self::MODE_TARGETS[$mode] ?? null;
    }

    public function resolveSearchMode(string $displayMode): string
    {
        return self::SEARCH_MODE_TARGETS[$displayMode] ?? self::DEFAULT_SEARCH_MODE;
    }

    public function isApproximatedSearchMode(string $displayMode): bool
    {
        return $displayMode === self::APPROXIMATED_SEARCH_MODE;
    }
}
