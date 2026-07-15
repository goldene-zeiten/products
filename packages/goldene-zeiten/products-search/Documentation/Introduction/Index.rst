:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_search adds catalog search and faceted browsing to EXT:products_core. It registers one
frontend plugin and does not touch the catalog itself — it only reads products, articles and
categories that already exist.

..  contents:: Table of contents
    :local:

..  _introduction-plugin:

Frontend plugin
=================

The extension registers one content element plugin:

..  confval:: Search

    A search box plus, depending on the content element's own fields, either free-text search
    results or one of five browse listings. See :ref:`Configuration <configuration>` for every
    field on the content element and exactly what each one does.

..  _introduction-two-engines:

Two independent engines behind one plugin
============================================

The plugin's controller,
:php:`GoldeneZeiten\Products\Search\Controller\SearchController`, dispatches to one of two
unrelated services depending on the content element's :guilabel:`Search Browse Mode` field:

*   :php:`GoldeneZeiten\Products\Search\Service\SearchService` runs the free-text search
    (:guilabel:`Search Browse Mode` = :guilabel:`Free text search`, the default). It always queries
    :php:`GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository` — **products only**,
    regardless of the content element's :guilabel:`Search Target` field. Results are paginated.

*   :php:`GoldeneZeiten\Products\Search\Service\FacetedBrowseService` runs every other browse mode
    (:guilabel:`Browse by first letter`, :guilabel:`Browse by year`, :guilabel:`Browse by field
    value`, :guilabel:`Browse by keyword multi-select`, :guilabel:`Most recent entries`). These
    modes do honour :guilabel:`Search Target` — products, articles or categories — and are not
    paginated: they either return every matching record grouped into buckets, or (for
    :guilabel:`Most recent entries`) a fixed 10 most-recent records.

..  note::
    :guilabel:`Search Target` has **no effect** while :guilabel:`Search Browse Mode` is
    :guilabel:`Free text search`. It only changes what the five browse modes read from. This is a
    real gotcha, not an oversight to plan around: a content element left on the default
    :guilabel:`Free text search` mode always searches the product catalog, however
    :guilabel:`Search Target` is set.

..  _introduction-not-full-text:

Substring matching, not a full-text engine
=============================================

Free-text search matches the term as a plain SQL :sql:`LIKE '%term%'` against five product
properties — title, subtitle, item number, description and EAN — combined with :sql:`OR`, so a hit
on any single field is enough. It is case-insensitive (ordinary database collation) and matches
anywhere in the field, not just at the start of a word: searching ``"shoe"`` finds a product titled
``"Running Shoes"`` as well as one titled ``"Shoehorn"``.

There is no relevance ranking, no stemming, no fuzzy/typo tolerance and no weighting between the
five fields — every match counts equally and results come back in the catalog's own sort order.
This is adequate for a catalog of moderate size; a shop with a very large catalog or a need for
"did you mean" / relevance-ranked results needs a real search engine (Solr, Elasticsearch, ...)
instead, which this extension does not provide or integrate with.

See :ref:`Configuration <configuration>` for the exact behaviour of every browse mode, with worked
examples.
