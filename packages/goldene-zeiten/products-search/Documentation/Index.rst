..  _start:

===============
Products Search
===============

:Extension key:
    products_search

:Package name:
    goldene-zeiten/products-search

:Version:
    |release|

:Language:
    en

:Author:
    Markus Hofmann

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

----

Catalog search and faceted browsing for the Products shop system.

----

Catalog search
==============

The :guilabel:`Search` plugin offers a simple search box; results match the term against a
product's title, subtitle, item number, description or EAN (case-insensitive, partial matches
count — e.g. searching "shoe" finds "Running Shoes"). It is not a full-text search engine: there is
no relevance ranking or fuzzy matching, which is adequate for a catalog of moderate size. Results
are paginated at :guilabel:`Search results per page` (default 20) per page.

Browse modes
============

Beyond free-text search, the plugin can browse the catalog without a search term at all. The
:guilabel:`Search Browse Mode` field of the content element selects one of:

..  confval:: text

    Free-text search (the default).

..  confval:: firstletter

    Groups the catalog by the first letter of the title, for an A–Z index.

..  confval:: year

    Groups by creation year.

..  confval:: field

    Groups by the exact value of the field named in :guilabel:`Search Field`.

..  confval:: keyfield

    Offers a multi-select of the distinct values of :guilabel:`Search Field`.

..  confval:: lastentries

    Lists the most recently created records first.

:guilabel:`Search Target` decides whether products, articles or categories are searched.

Installation
============

..  code-block:: bash

    composer require goldene-zeiten/products-search

Add the :guilabel:`Products Search` site set to your site, then place the :guilabel:`Search`
plugin on a page.

Settings
========

..  confval:: products.search.resultsPerPage
    :type: int
    :Default: 20

    Results shown per page by the :guilabel:`Search` plugin.
