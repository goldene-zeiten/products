:navigation-title: Users Manual

..  include:: /Includes.rst.txt
..  _users-manual:

=============
Users Manual
=============

This chapter is for editors and shop operators: how to put product search on a page and what shoppers see.
See :ref:`Configuration <configuration>` for the technical settings and
:ref:`Installation <installation>` for connecting a Solr server.

..  contents:: Table of contents
    :local:

..  _users-manual-plugin:

Placing the search plugin
=========================

Search is rendered by EXT:solr's own :guilabel:`Search` plugin — this extension only supplies the product
configuration behind it. On the page that should show search results, add an EXT:solr :guilabel:`Search`
content element (for example the results plugin), and point ``plugin.tx_solr.products.searchTargetPage`` at
that page so search forms elsewhere on the site submit to it. Nothing product-specific has to be maintained
on the plugin itself.

..  _users-manual-results:

What shoppers see
=================

Once products are indexed (see :ref:`Installation <installation>`), a search returns ranked product results
matching the query text against a product's title, subtitle, SKU, EAN and description. Each result links to
the product's real detail page. Alongside the results, shoppers can narrow down with facets:

*   **Category** — a flat list of category titles, and a category-tree facet following the category
    hierarchy.
*   **Attribute** — the product's attribute/variant values (for example a colour or size).
*   **Price** — price ranges (under 25, 25–50, 50–100, 100–250, over 250).

Which facets appear is controlled by the ``*.enable`` constants in
:ref:`Configuration <configuration-constants>`.
