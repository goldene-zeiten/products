:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

Every default this extension ships is overridable. The extension is configuration layered on top of
EXT:solr, so it is adjusted exactly the way you would configure EXT:solr itself: through the
``plugin.tx_solr.products.*`` TypoScript constants for the common knobs, and through the normal TypoScript
cascade over ``plugin.tx_solr.*`` for everything else.

..  contents:: Table of contents
    :local:

..  _configuration-constants:

TypoScript constants
====================

The shipped defaults are exposed as constants under ``plugin.tx_solr.products``. Override them in the site
set's constants, or in the :guilabel:`Constants` field of a TypoScript record, like any other constant.

..  confval-menu::
    :name: constants-overview
    :display: table
    :type:
    :Default:

    ..  confval:: plugin.tx_solr.products.indexPageIds
        :type: string (comma-separated int list)
        :Default: (empty)

        Storage folder UID(s) holding the product records. Empty indexes the whole site.

    ..  confval:: plugin.tx_solr.products.searchTargetPage
        :type: int
        :Default: 0

        UID of the page the search results are shown on (EXT:solr's ``search.targetPage``).

    ..  confval:: plugin.tx_solr.products.resultsPerPage
        :type: int
        :Default: 12

        Number of results per page.

    ..  confval:: plugin.tx_solr.products.priceFacet.enable
        :type: boolean
        :Default: 1

        Show the price-range facet.

    ..  confval:: plugin.tx_solr.products.categoryFacet.enable
        :type: boolean
        :Default: 1

        Show the category and hierarchical category-tree facets.

    ..  confval:: plugin.tx_solr.products.attributeFacet.enable
        :type: boolean
        :Default: 1

        Show the product attribute/variant facet.

Anything the constants do not expose is still adjustable through the ordinary TypoScript cascade over
``plugin.tx_solr.*`` — the facet definitions, the result rendering, the Index Queue mapping — because the
extension's own setup is nothing more than a TypoScript layer on EXT:solr.

..  _configuration-index-queue:

Index Queue field mapping
=========================

The Index Queue maps each ``tx_products_domain_model_product`` record to a Solr document. The mapping
**reuses EXT:solr's shipped default schema fields wherever one exists** — ``title``, ``subTitle``,
``description``, ``content``, ``category``, ``price``, ``url`` and ``image`` — and only falls back to a
dynamic field where the default schema has none:

*   ``itemNumber_stringS`` — the SKU / item number
*   ``ean_stringS`` — the EAN
*   ``isOffer_boolS`` / ``isHighlight_boolS`` — product flags
*   ``attribute_stringM`` — the product attribute/variant values for the attribute facet
*   ``categoryPath_stringM`` — the depth-prefixed category hierarchy paths for the category-tree facet

Because the mapping stays within EXT:solr's default configset, the Solr server's configset **must not be
modified**. No custom schema fields are added on the Solr side; the dynamic-field naming conventions
(``*_stringS``, ``*_boolS``, ``*_stringM``) are what EXT:solr's default schema already provides.

..  _configuration-detail-url:

Product detail URLs
===================

The indexed ``url`` field is built from the products-core detail page constant
``{$plugin.tx_productscore.settings.pids.detailPage}``, using the same ``ProductDetail`` plugin arguments
the shop's own catalog uses for its product links. Indexed result links therefore point at the shop's real
product detail pages, and the site's route enhancers rewrite them to the real product slug. Make sure that
constant resolves to the shop's detail page on the indexing rootline, or indexed links will be wrong.
