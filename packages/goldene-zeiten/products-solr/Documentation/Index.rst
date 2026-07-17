..  include:: /Includes.rst.txt

..  _start:

=====================
Products Solr Search
=====================

:Extension key:
   products_solr

:Package name:
   goldene-zeiten/products-solr

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

:Rendered:
   |today|

----

Apache Solr backed product search for the Products shop system: a faster, more scalable alternative to the
MySQL-based ``products_search``, built on EXT:solr's indexing and search plugins with a ready-made default
configuration for the product catalog.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension provides, and how it relates to the shop's built-in ``products_search``.

    ..  card:: :ref:`Installation <installation>`

        How to install the extension and connect a Solr server.

    ..  card:: :ref:`Configuration <configuration>`

        The shipped defaults, the TypoScript constants, and the index-queue field mapping.

    ..  card:: :ref:`Users Manual <users-manual>`

        What editors and shoppers see once the search plugin is on a page.

    ..  card:: :ref:`Developer <developer>`

        Extension points: the indexing userFunc helpers, template partials and facet TypoScript.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
