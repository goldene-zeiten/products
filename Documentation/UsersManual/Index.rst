:navigation-title: Users Manual

..  include:: /Includes.rst.txt
..  _users-manual:

=============
Users Manual
=============

This chapter is for editors and shop operators: what you can do with the extension day to day, once
it is installed and configured. See :ref:`Configuration <configuration>` for technical settings.

..  contents:: Table of contents
    :local:

..  _users-manual-catalog:

Managing the catalog
=====================

Categories, products and articles are managed in the :guilabel:`Products` backend module (not in the
page tree list module, since these records are not organised by page). Open it via the main module
menu entry :guilabel:`Products`.

*   Use the tree on the left to navigate categories. Right-click (or the toolbar) offers
    :guilabel:`New category`, and dragging a category onto another re-parents it.
*   Selecting a category shows its products on the right; selecting a product shows its articles.
*   Use the :guilabel:`New product in this category` / :guilabel:`New article for this product`
    actions to create records in place; editing opens the normal TYPO3 record edit form.

Articles are purchasable variants of a product (e.g. a specific size or colour). A product with
articles can only be ordered via one of its articles — the product itself is then just the shared
catalog entry (title, description, categories); price, stock and EAN are set per article. A product
without any articles is directly purchasable itself.

..  _users-manual-cross-selling:

Cross-selling: related and accessory products
===============================================

Each product has two independent, editor-curated lists on its edit form:

*   :guilabel:`Related Products` — alternatives or similar items, shown as a "You might also like"
    section near the bottom of the product detail page.
*   :guilabel:`Accessory Products` — complementary add-ons, shown as a compact "Frequently bought
    with" list next to the add-to-basket area.

Both are picked manually (there is no automatic "customers who bought this also bought" logic) and
are one-directional: adding product B as related to product A does not automatically show A as
related on B's page — add the relation on both sides if you want it to show both ways. Leaving
either list empty simply hides that section; there is no minimum.

..  _users-manual-media:

Product media: images and downloads
====================================

Products, articles and categories each have an :guilabel:`Images` (or, for categories, a single
:guilabel:`Image`) field, plus an optional :guilabel:`Downloads` field on products and articles for
attachments such as data sheets or manuals — filled in like any other file field in the TYPO3
backend (drag & drop, or the file browser).

*   The first image in a product's gallery is used as its teaser image in product listings; the full
    gallery is shown on the product detail page.
*   An article's own :guilabel:`Images` field is optional. Leave it empty to show the product's
    gallery instead (the same "leave empty to inherit" convention used for the article price) — only
    fill it in when a specific variant (e.g. a colour) needs its own pictures.
*   Downloads are shown as a plain file list on the product detail page; they carry no access
    restriction beyond the record's own visibility (hidden/start-end time).

..  _users-manual-pricing:

Graduated (quantity-based) pricing
===================================

Both products and articles have a :guilabel:`Graduated Prices` section where you can add rows of
"from quantity" / "unit price" — a classic bulk-discount price list. When a customer's basket line
reaches a tier's quantity, that tier's price is used instead of the regular price; the highest
tier reached "from quantity" that is not above the ordered quantity wins.

*   An article's own graduated prices override the product's for that article; if the article has
    none configured, the product's graduated prices still apply to it.
*   Leave the section empty to sell at the regular (non-graduated) price, exactly as before.
*   The product detail page shows the full price list to shoppers, and lists it as
    "from" a starting price wherever the regular price would otherwise be shown.

..  _users-manual-variants:

Variant attributes (size, colour, ...)
=======================================

Manage reusable attributes (e.g. "Size", "Colour") and their values in the storage folder's record
list, or via the :guilabel:`Products` backend module. Each article can then be tagged with a
:guilabel:`Variant Attributes` selection (e.g. "Size: L" and "Colour: Red" together).

*   Once at least one article of a product has variant attributes, the product detail page shows a
    dropdown per attribute instead of the plain article list; shoppers pick one value per attribute
    and the matching article is added to the basket.
*   Articles without any variant attributes keep working exactly as before (plain title dropdown) —
    this is optional, not a requirement for every product.
*   Every attribute value used by a product's articles shows up in that product's dropdowns; values
    not used by any of that product's articles are not filtered out of the list — pick a
    combination that has a matching article, or nothing is added to the basket.

..  _users-manual-orders:

Managing orders
================

The :guilabel:`Orders` submodule (under the :guilabel:`Products` main module) lists all orders,
filterable by status, order number and email. Opening an order shows a summary (date, email,
status, payment status, payment method, total, customer note) and the actions available for it:

*   :guilabel:`Mark paid` — sets the payment status to "paid". Shown only while the order's current
    payment status can actually move there (mirrors the invoice workflow: confirm payment once the
    bank transfer arrives).
*   :guilabel:`Set status: ...` — one button per status the order can legally move to next (e.g.
    "confirmed" → "shipped" → "completed", or "cancelled" from an early status). Illegal transitions
    are never offered, so there is no way to put an order into an invalid state from this module.

This module only manages status; full order details (line items, addresses) remain in the record
edit view for now — a fuller order-detail view is a good candidate for a later milestone.

A :guilabel:`Refund` action also appears once an order's payment status can move to "refunded" and
its payment method supports refunds. The invoice payment method shipped with this extension
supports it as an acknowledgement (there is no gateway to call back — refunding an invoice payment
just records that the money was returned outside the system); third-party payment methods can
implement the same contract to wire up a real refund call.

..  _users-manual-category-permissions:

Category permissions
======================

Beyond the coarse-grained :ref:`category mounts <configuration-backend-module>` (which subtree a
user/group can see at all), each category has an :guilabel:`Access` tab — the same owner-user/
owner-group/everybody permission model pages use, with Show/Edit/Delete/New Subcategories
checkboxes per owner. This is *additive* to mounts: a category outside a user's mounts is invisible
regardless of its permissions, and a category with permissions denying "Edit" cannot be changed by
that user even though they can see it.

Leaving all :guilabel:`Access` checkboxes empty on a category means "no one but an admin can edit
it" — set at least :guilabel:`Everybody` → :guilabel:`Edit` to keep the previous mounts-only
behaviour where any user with mount access could also edit.
