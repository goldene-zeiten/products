:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_solr adds Apache Solr backed product search to EXT:products_core. It is a faster, more
scalable alternative to the MySQL ``LIKE`` search of ``products_search``: instead of querying the database
row by row, shoppers search a Solr index that returns ranked, full-text results together with category,
attribute and price-range facets.

..  contents:: Table of contents
    :local:

..  _introduction-what-it-provides:

What it provides
=================

The extension does not reinvent search. It reuses EXT:solr's own Index Queue and search plugins and ships
a ready-made default configuration for the product catalog:

*   full-text search over a product's title, subtitle, SKU (item number), EAN and description,
*   a category facet and a hierarchical category-tree facet,
*   a product attribute/variant facet, and
*   a price-range facet.

The only PHP in the package is a small Index Queue helper
(:php:`GoldeneZeiten\Products\Solr\Indexing\ProductIndexFieldMapper`) that resolves the two facet values a
flat TypoScript mapping cannot express — see :ref:`Developer <developer>`. Everything else is configuration
layered on top of EXT:solr.

..  _introduction-relationship:

Relationship to products_search
================================

This extension is **self-contained**: it does not depend on, and is not meant to run alongside,
``products_search``. The two are alternative search back ends for the same shop — install one or the other,
not both. Choose ``products_solr`` when the catalog is large enough, or search important enough, that a real
search engine with facets is worth running a Solr server for; keep ``products_search`` when the shop is
small and adding an infrastructure dependency is not warranted.
