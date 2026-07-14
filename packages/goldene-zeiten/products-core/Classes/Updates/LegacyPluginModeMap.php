<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

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
        'LIST' => ['ctype' => 'productscore_productlist', 'fields' => ['tx_products_list_mode' => 'all']],
        'LISTOFFERS' => ['ctype' => 'productscore_productlist', 'fields' => ['tx_products_list_mode' => 'offers']],
        'LISTHIGHLIGHTS' => ['ctype' => 'productscore_productlist', 'fields' => ['tx_products_list_mode' => 'highlights']],
        'LISTNEWITEMS' => ['ctype' => 'productscore_productlist', 'fields' => ['tx_products_list_mode' => 'new']],
        'LISTAFFORDABLE' => ['ctype' => 'productscore_productlist', 'fields' => ['tx_products_list_mode' => 'affordable']],
        'LISTARTICLES' => ['ctype' => 'productscore_productlist', 'fields' => ['tx_products_list_mode' => 'articles']],
        'LISTVIEWEDITEMS' => ['ctype' => 'productscore_recentlyviewed', 'fields' => ['tx_products_recentlyviewed_mode' => 'recent']],
        'LISTVIEWEDMOST' => ['ctype' => 'productscore_recentlyviewed', 'fields' => ['tx_products_recentlyviewed_mode' => 'mostviewed']],
        'LISTVIEWEDMOSTOTHERS' => ['ctype' => 'productscore_recentlyviewed', 'fields' => ['tx_products_recentlyviewed_mode' => 'mostviewedglobal']],
        'SINGLE' => ['ctype' => 'productscore_productdetail', 'fields' => []],
        'SEARCH' => ['ctype' => 'productscore_search', 'fields' => ['tx_products_search_browse_mode' => 'text']],
        'MEMO' => ['ctype' => 'productscore_wishlist', 'fields' => []],
        'BASKET' => ['ctype' => 'productscore_basket', 'fields' => []],
        'ORDERS' => ['ctype' => 'productscore_orderhistory', 'fields' => []],
        'BILL' => ['ctype' => 'products_invoice', 'fields' => []],
        'WITHDRAWAL' => ['ctype' => 'productscore_withdrawal', 'fields' => []],
        'DOWNLOAD' => ['ctype' => 'productscore_download', 'fields' => []],
        'LISTCAT' => ['ctype' => 'productscore_categorylist', 'fields' => []],
        'SELECTCAT' => ['ctype' => 'productscore_categorynavigation', 'fields' => ['tx_products_navigation_style' => 'dropdown']],
        'MENUCAT' => ['ctype' => 'productscore_categorynavigation', 'fields' => ['tx_products_navigation_style' => 'menu']],
        'INFO' => ['ctype' => 'productscore_checkout', 'fields' => []],
        'PAYMENT' => ['ctype' => 'productscore_checkout', 'fields' => []],
        'FINALIZE' => ['ctype' => 'productscore_checkout', 'fields' => []],
        'DELIVERY' => ['ctype' => 'productscore_checkout', 'fields' => []],
        'TRACKING' => ['ctype' => 'productscore_checkout', 'fields' => []],
        'OVERVIEW' => ['ctype' => 'productscore_checkout', 'fields' => []],
    ];

    /**
     * tt_products_pi_search has its own display_mode vocabulary.
     *
     * @var array<string, string>
     */
    private const SEARCH_MODE_TARGETS = [
        'TEXTFIELD' => 'text',
        'FIRSTLETTER' => 'firstletter',
        'YEAR' => 'year',
        'LASTENTRIES' => 'lastentries',
        'FIELD' => 'field',
        'KEYFIELD' => 'keyfield',
    ];

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
}
