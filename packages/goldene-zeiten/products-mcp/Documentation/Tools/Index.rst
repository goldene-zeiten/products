:navigation-title: Tools

..  include:: /Includes.rst.txt
..  _tools:

=====
Tools
=====

The read tools let an assistant look around the shop; the :ref:`basket tools <tools-basket>` let it collect
products for the customer and :ref:`hand the basket over <tools-start-checkout>`. They answer in the language of the site language the
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

..  _tools-basket:

Basket tools
============

An assistant builds a basket on the server. It is not the browser basket of any customer: the basket is stored
under a random ``basketToken`` that ``create_basket`` returns and every other basket tool asks for. A basket
expires :confval:`after the checkout link lifetime <products.mcp.checkoutLinkLifetime>` without a change; an
unknown or expired token is a tool error.

Products are added through the basket service of the shop, so a basket line is priced, taxed and addressed
exactly like a line in the customer's basket, including options. The prices are the customer-independent ones:
discounts of a customer group are applied once the customer is in the checkout.

..  _tools-create-basket:

create_basket
-------------

Takes no input and returns ``basketToken``.

..  _tools-add-to-basket:

add_to_basket
-------------

Input
    ``basketToken`` (required), ``product`` (uid, required), ``article`` (uid of the variant), ``quantity``
    (1 by default) and ``selections`` (object of option identifier to value, for products with line options
    such as an engraving).

Adding a product the basket already holds raises its line. The tool returns the basket as ``get_basket`` does.
These are tool errors that say why:

*   the product does not exist, is hidden or not visible to the customer,
*   a product with variants was added without ``article``, or the article is not a variant of the product,
*   the quantity in the basket would be below the minimum or above the maximum of the product or variant,
*   the quantity in the basket would exceed the stock (the frontend basket only checks the stock when the
    order is placed; the assistant is told at once), or
*   a listener refuses the ``selections``.

The limits and the stock count all lines of the same product or variant, whatever their options.

..  _tools-update-basket-item:

update_basket_item
------------------

Input
    ``basketToken``, ``line`` and ``quantity``, all required. ``line`` is the ``line`` key of a line in
    ``get_basket``, such as ``3:71:`` (product, article or 0, key of the options). Quantity ``0`` removes the
    line.

Returns the basket. A line that is not in the basket, and a quantity the limits or stock do not allow, are tool
errors.

..  _tools-get-basket:

get_basket
----------

Input
    ``basketToken``.

Output
    ``currency``; ``itemCount``; ``lines``, each with ``line``, ``product``, ``title``, ``article`` (``uid``,
    ``title``, ``itemNumber`` or ``null``), ``options`` (``label``, ``value``, ``surcharge``), ``quantity``,
    ``vatRate`` (percent), ``unitPrice`` and ``lineTotal`` (each with ``net`` and ``gross``); ``totals`` with
    ``net``, ``tax`` and ``gross``; ``vat``, the totals per VAT rate. Shipping and payment fees are not
    included; they depend on the customer's address and choices in the checkout.

..  _tools-start-checkout:

start_checkout
--------------

Input
    ``basketToken``.

Output
    ``checkoutUrl``, an absolute address below the MCP path (``/mcp/checkout?token=...``), and ``expiresAt``
    (ISO 8601, UTC). An empty basket is a tool error.

The assistant gives the link to the customer. The token in it is signed with an HMAC over the installation's
encryption key and carries its expiry (:confval:`products.mcp.checkoutLinkLifetime`); the customer cannot swap
the basket or extend the link.

Opening the link in a browser:

#.  verifies signature and expiry,
#.  adds the lines of the assistant's basket to the basket in the visitor's own frontend session, through the
    basket service again, so a line that cannot be sold any more is left out and the lines add to what the
    visitor already collected, and
#.  redirects with ``303 See Other`` to the checkout page (``products.pids.checkoutPage``, or the basket page
    while none is set).

A basket is merged once per browser session, so reloading the link or going back to it does not double the
quantities. The link can be opened by several visitors (it is a bearer link, like a shared basket); each gets
the lines in their own session.

A link that is malformed, tampered with or expired, or whose basket has expired, merges nothing and redirects
with ``303`` to the basket page (``products.pids.basketPage``, or the site root), where the visitor sees the
basket they have. The order itself is never placed through MCP: terms, the withdrawal waiver and payment happen
in the checkout.
