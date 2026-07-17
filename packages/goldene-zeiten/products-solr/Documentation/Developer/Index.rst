:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

===================================
Developer / Extension Points
===================================

This extension has no backend module and no Extbase controllers of its own. Its only PHP is one Index Queue
helper; everything else — the field mapping, the facets, the result rendering — is TypoScript and Fluid you
override the EXT:solr way.

..  contents:: Table of contents
    :local:

..  _developer-index-field-mapper:

The ProductIndexFieldMapper userFunc helpers
=============================================

:php:`GoldeneZeiten\Products\Solr\Indexing\ProductIndexFieldMapper` supplies the two facet values a flat
TypoScript ``SOLR_RELATION`` cannot express. Both methods are called as ``USER`` cObjects from the Index
Queue field mapping (``plugin.tx_solr.index.queue.products.fields``); EXT:solr sets the current product row
on the object's :php:`$cObj`, and each returns a ``|``-separated list split into a multi-value Solr field.

*   :php:`attributeValues()` (field ``attribute_stringM``) resolves the **multi-hop** attribute values
    ``Product -> Article -> AttributeValue -> Attribute``, emitting ``"Attribute: Value"`` labels for the
    attribute facet. A flat MM relation cannot walk that many hops.
*   :php:`categoryPaths()` (field ``categoryPath_stringM``) resolves the **depth-prefixed category
    hierarchy paths** Solr's hierarchy facet expects — a leaf under ``Root/Child`` yields
    ``0-/Root``, ``1-/Root/Child``, ``2-/Root/Child/Leaf`` — by walking each category's parent chain in
    PHP. A flat MM relation cannot express the tree.

The flat category facet, by contrast, is a plain ``SOLR_RELATION`` in TypoScript (``category_stringM``); only
the tree and the multi-hop attributes need PHP.

..  _developer-result-partial:

Overriding the result rendering
===============================

Result rendering is EXT:solr's Fluid, with this extension's template paths registered first
(``plugin.tx_solr.view.templateRootPaths.100`` / ``partialRootPaths.100`` / ``layoutRootPaths.100``, under
``EXT:products_solr/Resources/Private/``). The shipped ``Result/Document`` partial replaces EXT:solr's
generic result item with a product card built from the indexed ``title``, ``url``, ``price`` and ``image``
fields.

To restyle results, register your own template root path at a higher index (``.200`` and up) in your site's
TypoScript and provide your own ``Result/Document`` partial — the highest index wins, so you override this
extension's partial without touching the package:

..  code-block:: typoscript

    plugin.tx_solr.view {
        templateRootPaths.200 = EXT:my_extension/Resources/Private/Solr/Templates/
        partialRootPaths.200 = EXT:my_extension/Resources/Private/Solr/Partials/
    }

..  _developer-facet-typoscript:

Overriding the facet TypoScript
===============================

The facets are defined under ``plugin.tx_solr.search.faceting.facets`` (``category``, ``categoryTree``,
``attribute``, ``price``). Each is toggled by an ``if.isTrue`` guard bound to the ``*.enable`` constants —
so the simplest change is flipping a constant (see :ref:`Configuration <configuration-constants>`). For
anything deeper — new query groups on the price facet, a different facet label, an added facet on another
indexed field — override the corresponding ``plugin.tx_solr.search.faceting.facets.*`` key in your own
TypoScript setup, layered after this extension's site set. It is ordinary EXT:solr faceting configuration;
nothing here is special-cased.
