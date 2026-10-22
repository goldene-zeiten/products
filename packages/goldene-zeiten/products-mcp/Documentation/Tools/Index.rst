:navigation-title: Tools

..  include:: /Includes.rst.txt
..  _tools:

=====
Tools
=====

The read tools let an assistant look around the shop. They answer in the language of the site language the
endpoint was called on, read the products from the storage folder of the site (``products.pids.storageFolder``)
and leave out hidden products, like the frontend. Every tool returns a JSON object, both as structured content
and as JSON text. A call that cannot be answered, such as a product that does not exist, is returned as a tool
error with a message the assistant can read.

Prices are shown the way the shop shows them: the unit price of the price provider the product templates use, in
the pricing mode of the site. ``includesVat`` tells whether the amount contains VAT.

..  contents:: Table of contents
    :local:

..  _tools-price:

Price object
============

``amount``
    The price as a decimal string, for example ``119.00``.

``includesVat``
    ``true`` while the site shows prices including VAT (pricing mode ``gross``).

``currency``
    The currency code of the site, for example ``EUR``.

``from``
    ``true`` for a product with variants of different prices, where ``amount`` is the cheapest variant.

..  _tools-search-products:

search_products
===============

Searches the products by a term in title, subtitle, item number, description and EAN, like the shop search.

Input
-----

``query`` (string)
    Search term. Empty lists all products.

``category`` (integer)
    Uid of a category. Products of its subcategories are included.

``manufacturer`` (integer)
    Uid of a manufacturer.

``minPrice``, ``maxPrice`` (number)
    Price range in the pricing mode of the shop. A product without variants is matched by its own price, a
    product with variants when any of its variants is within the range.

``page`` (integer)
    Page number, starting at 1.

``perPage`` (integer)
    Products per page, 10 by default and at most 50.

Output
------

``total``
    Number of products found.

``page``, ``perPage``
    The page returned and the page size applied.

``items``
    The products of the page, each with ``uid``, ``title``, ``itemNumber``, ``price`` (:ref:`price object
    <tools-price>`), ``inStock``, ``manufacturer`` (brand name), ``category`` (title of the first category) and
    ``url`` (absolute address of the detail page, empty while ``products.pids.detailPage`` is not set).

..  _tools-get-product:

get_product
===========

Describes one product in full.

Input
-----

``uid`` (integer, required)
    Uid of the product.

Output
------

``uid``, ``title``, ``subtitle``, ``itemNumber``, ``price``, ``inStock``, ``url``
    As in the search result.

``description``
    The description as plain text, without markup.

``variants``
    The articles of the product, each with ``uid``, ``title``, ``itemNumber``, ``attributes`` (attribute title to
    value), ``price``, ``stock`` and ``unlimitedStock``.

``manufacturer``
    ``brand``, ``name`` and ``website`` of the manufacturer, or ``null``.

``categories``
    The titles of the categories of the product.

A product that does not exist or is not visible is a tool error.

..  _tools-list-categories:

list_categories
===============

Walks the category tree one level at a time.

Input
-----

``parent`` (integer)
    Uid of the category whose subcategories to list. Without it, the top-level categories are listed. An unknown
    category is a tool error.

Output
------

``categories``
    Each with ``uid``, ``title``, ``hasChildren`` and ``productCount``, the number of products directly in the
    category.

..  _tools-list-manufacturers:

list_manufacturers
==================

Lists the manufacturers that have products.

Input
-----

``category`` (integer)
    Uid of a category. Only manufacturers with a product in the category or its subcategories are listed. An
    unknown category is a tool error.

Output
------

``manufacturers``
    Each with ``uid``, ``brand``, ``name`` and ``website``.

..  _tools-get-shipping-options:

get_shipping_options
====================

Quotes the shipping options for a destination and the items to ship, with the shipping providers of the shop.

Input
-----

``country`` (string, required)
    Destination country as ISO 3166-1 alpha-2 code, for example ``DE``.

``items`` (array)
    The items to ship, each with ``product`` (uid, required), ``article`` (uid of the variant) and ``quantity``
    (1 by default). A product that is not visible, or an article that does not belong to the product, is a tool
    error. Without items the options for an empty basket are quoted; providers that decide on weight or value
    can offer different options for a real basket.

Output
------

``country``, ``currency``
    The destination and the currency of the site.

``goodsTotal``
    The value of the items including VAT, which carriers may decide on.

``options``
    Each with ``key``, ``label``, ``cost`` (decimal string) and ``deliveryEstimate``. The list is empty while
    shipping is disabled for the site or no provider ships to the country.
