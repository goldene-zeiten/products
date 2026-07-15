..  include:: /Includes.rst.txt

..  _start:

===================
Products API Client
===================

:Extension key:
    products_api_client

:Package name:
    goldene-zeiten/products-api-client

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

Shared OAuth 2.0 client-credentials and PSR-18 HTTP plumbing for the Products shop system's
third-party API integrations, so a carrier or payment gateway integration does not reimplement
token handling and request building on its own.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this library provides and the problem it solves for integration packages.

    ..  card:: :ref:`Installation <installation>`

        Requirements and how a package depends on this library.

    ..  card:: :ref:`Developer <developer>`

        The three public services, worked examples, and how a consumer wires its own token
        cache.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Developer/Index
