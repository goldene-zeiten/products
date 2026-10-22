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
*   With several root categories - e.g. a shop tree next to manufacturer and filter categories - a select
    above the tree chooses which root the tree shows; the choice is remembered. The search finds records under
    that root only, and a new category or product dropped on the tree background goes into that root. Moving a
    category to another root is done in its record form.
*   Use the :guilabel:`New product in this category` / :guilabel:`New article for this product`
    actions to create records in place; editing opens the normal TYPO3 record edit form.

Articles are purchasable variants of a product (e.g. a specific size or colour). A product with
articles can only be ordered via one of its articles — the product itself is then just the shared
catalog entry (title, description, categories); price, stock and EAN are set per article. A product
without any articles is directly purchasable itself. Until a customer chooses an article, lists and the
detail page show the price of the cheapest article as "From …", and the structured data for search engines
offers the price range of all articles.

A :guilabel:`Direct Cost` field on both products and articles records what the item costs the
merchant — purely an internal reference for margin calculation, never shown to shoppers or on any
frontend page.

An :guilabel:`Unlimited stock` flag on both products and articles bypasses stock tracking entirely
for that item: it is always shown as available, never decremented when ordered, and never blocks
checkout on insufficient stock — either the product's or the selected article's flag being set is
enough (there is no "override" precedence between the two, since a boolean has no way to represent
"not set" separately from "false").

:guilabel:`Min Quantity in Basket` / :guilabel:`Max Quantity in Basket` on both products and
articles enforce an order-quantity limit at the basket (0 on either field means no limit). An
article's own non-zero value overrides the product's; when it is 0, the product's own limit
applies instead. A requested quantity outside the bounds is clamped into range, the same way it
behaves when only a maximum (or only a minimum) is set.

Product page titles
--------------------

`products.seo.pageTitleMode` controls whether a product's own title/subtitle substitutes the page
title on its :rst:dir:`ProductDetail` page (the default, ``title``, does this) or whether the
page's own configured title is left alone (``none``). This only affects the single-product page's
``<title>`` tag, never listings or other pages.

Search engines and social media
-------------------------------

The :guilabel:`SEO` tab of a product holds the texts search engines and social networks show:
:guilabel:`SEO title` (the page title, instead of the title built by
``products.seo.pageTitleMode``), :guilabel:`Meta description`, :guilabel:`Keywords`,
:guilabel:`Hide from search engines (noindex)` and, for social media, the Open Graph title,
description and image. Every field may stay empty:

*   the description falls back to the product text, without markup and cut after about 150
    characters;
*   the Open Graph title falls back to the SEO title, then the product title; the Open Graph
    description to the description; the image to the product's first image.

An article has the same fields. A filled field overrides the product's value on that article's
page; an empty one keeps the product's. The detail page also carries the product as schema.org
``Product`` data (JSON-LD) with its price, currency and availability.

With EXT:seo installed, a product detail page names its canonical URL - the product's URL, or the
article's own URL when the article has an SEO title or meta description of its own - and the
products appear in the XML sitemap (``/?type=1533906435&sitemap=products``, linked from the
sitemap index). Products hidden from search engines are left out of it.

Product lists: categories and filter categories
-----------------------------------------------

A :guilabel:`Product list` shows the products of any of its :guilabel:`Category Restriction` categories,
including their subcategories. :guilabel:`Filter categories` narrow that list down: a product is only listed
when it is also in every filter category (or one of its subcategories). "Engagement rings" as category and
"Champagne gold" as filter category list the engagement rings in champagne gold, not every engagement ring
and every champagne gold product.

Product lists: page size, order and page links
------------------------------------------------

A :guilabel:`Product list` and a :guilabel:`Category list` content element have a
:guilabel:`Listing` palette:

:guilabel:`Products per page`
    How many products one page shows. ``0`` shows the whole list on one page.
:guilabel:`Link the further pages`
    Shows page links below the list. Switched off, the element shows only the first page - a
    "four highlights" teaser, for example.
:guilabel:`Sort order`
    Manual (the order in the backend), title, price ascending or descending, or newest first.
    The :guilabel:`New items` list shows the newest first unless another order is chosen.

Page links lead to speaking URLs: ``/page/2`` below a product list, ``/<category path>/page/2``
on a category page. How many page numbers appear around the current one is set by
:confval:`products.list.maximumNumberOfLinks`.

Archiving old products
------------------------

The :guilabel:`Products` backend module's :guilabel:`Archive old products…` action moves products
and articles created more than a given number of days ago (housekeeping only — it never touches
what shoppers actually see unless the destination page is later removed from the site) from a
source page to a destination page you pick, via a plain age-threshold/destination-pid form. Both
the source and destination must be Sysfolder pages (TYPO3's normal storage-folder doktype); this
follows directly from how TYPO3's core `DataHandler` permission checks work, not a limitation
specific to this extension. A flash message summarises how many records moved, or reports any
failure (e.g. a non-Sysfolder destination) instead of moving nothing silently.

..  _users-manual-manufacturers:

Manufacturers and product safety information
=============================================

Create the makers of your products as :guilabel:`Manufacturer` records in the same folder as the products
and pick one in the :guilabel:`Manufacturer` field of each product. A variant (article) uses the manufacturer
of its product.

The detail page shows the manufacturer in the block "Manufacturer information" and the product's structured
data (JSON-LD) carries it as ``brand`` and ``manufacturer``.

The EU General Product Safety Regulation (EU) 2023/988, Art. 19, requires an online offer to show:

*  the manufacturer's name (registered trade name or trademark): :guilabel:`Name` and, if the products are sold
   under a different trademark, :guilabel:`Brand name`
*  the manufacturer's postal address: :guilabel:`Street and number`, :guilabel:`ZIP code`, :guilabel:`City`
   and :guilabel:`Country`
*  the manufacturer's electronic address: :guilabel:`Email address` or :guilabel:`Contact URL`
*  for a manufacturer outside the EU, the responsible person in the EU, on the tab
   :guilabel:`EU responsible person` (name, postal address, email)

The description, the logo and the link to a page are optional and not part of the legal minimum.

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
*   Downloads are what a customer buys with the product. The product detail page only names them;
    the files themselves are delivered by the :guilabel:`Download` content element to the customer
    of an order (logged in, or via the order link from the confirmation email), and only once the
    order's payment has been received - an invoice order after it has been marked as paid. A
    cancelled or withdrawn order and a refunded payment withdraw the downloads again.

..  important::

    Store download files in a file storage that is **not publicly accessible** (a storage whose
    :guilabel:`Is publicly available?` option is off, or whose folder lies outside the web root).
    The download plugin streams the file itself, but a file in a public storage such as
    :file:`fileadmin/` can also be fetched directly by anyone who knows or guesses its URL,
    bypassing the order check.

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

..  _users-manual-usergroup-discounts:

FE-usergroup discounts
=========================

Beyond graduated pricing, a shopper's basket price can be reduced by a personal or group discount
— useful for wholesale customers, staff, or any other segment that should pay less than the
regular price without a voucher code:

*   :guilabel:`Discount Percent` on a frontend user group applies to every member of that group.
*   :guilabel:`Discount Percent` on a frontend user's own record applies to that one customer.
*   A shopper in more than one discounted group, or with both a personal and a group discount,
    gets the single best (highest) rate rather than the sum of all of them — never stacked, the
    same "highest one wins" behaviour legacy had.

Both fields default to ``0.00`` (no discount) and are found on the group/user's edit form. The
discount is applied on top of any graduated-pricing tier already reached, and only affects the
basket/checkout price shown to that shopper — it has no effect for guests or non-discounted
customers.

..  _users-manual-category-discounts:

Category-cascading discounts
===============================

Both products and categories have a :guilabel:`Discount Percent` field and a
:guilabel:`Discount disabled` flag, letting a discount cascade down a category tree instead of
being set on every single product:

*   A product's own :guilabel:`Discount Percent`, if set, is always used directly — it never looks
    at any category.
*   Otherwise, `products.pricing.discountFieldMode` decides how the category tree is searched:
    ``maxAcrossTree`` (the default) takes the highest discount found anywhere in the product's full
    ancestor chain; ``nearestCategory`` takes the first discount found walking upward from the
    product's own category, stopping at the first one found.
*   A category's own :guilabel:`Discount disabled` flag only matters in ``nearestCategory`` mode,
    where it acts as a hard stop — nothing above a disabled category is inherited by anything below
    it. In ``maxAcrossTree`` mode, only a *product's* own :guilabel:`Discount disabled` flag has any
    effect (it zeroes that product's discount regardless of any category).

When a product also qualifies for graduated pricing or a FE-usergroup discount at the same time,
the single best (highest) rate across all three sources wins — never stacked, the same
"highest one wins" precedent :ref:`FE-usergroup discounts <users-manual-usergroup-discounts>`
already established.

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
*   Picking a combination updates the shown price and stock for the matching article. By default
    this reloads the page (`products.catalog.articleSwitchMode`); switch that setting to ``ajax``
    to swap the price/stock in place instead, without a full page reload.

An article's :guilabel:`Price Mode` controls how its own :guilabel:`Price` field (when non-zero)
combines with the product's: :guilabel:`Override` (the default) replaces the product's price
entirely; :guilabel:`Surcharge` instead adds the article's price on top of the product's — useful
for e.g. "+5.00 for size XL" variants without re-entering the full price on every article. A price
of 0.00 always means "no override/surcharge, inherit the product's price", regardless of mode.

..  _users-manual-orders:

Managing orders
================

The :guilabel:`Orders` submodule (under the :guilabel:`Products` main module) lists all orders,
filterable by status, order number and email. Opening an order shows a summary (date, email,
status, payment status, payment method, total, customer note — plus any applied voucher code(s),
the combined voucher/credit-points discount, and the shipping option the customer chose with its
cost, when present) and the actions available for it:

*   :guilabel:`Mark paid` — sets the payment status to "paid". Shown only while the order's current
    payment status can actually move there (mirrors the invoice workflow: confirm payment once the
    bank transfer arrives).
*   :guilabel:`Set status: ...` — one button per status the order can legally move to next (e.g.
    "confirmed" → "shipped" → "completed", or "cancelled" from an early status). Illegal transitions
    are never offered, so there is no way to put an order into an invalid state from this module.

This module only manages status; full order details (line items, addresses) remain in the record
edit view for now — a fuller order-detail view is a good candidate for a later milestone.

A :guilabel:`Discounts & rewards` section on the detail view lists every voucher redemption for
that order, whether it generated a gained bonus voucher (and whether that code has been used yet),
and its credit-points ledger entries (earned, redeemed, or manually adjusted) — everything that
otherwise only lives in separate record lists, gathered in one place per order.

A :guilabel:`Refund` action also appears once an order's payment status can move to "refunded" and
its payment method supports refunds. The invoice payment method shipped with this extension
supports it as an acknowledgement (there is no gateway to call back — refunding an invoice payment
just records that the money was returned outside the system); third-party payment methods can
implement the same contract to wire up a real refund call. Only one refund can be in flight per
order: while a gateway has accepted a refund but not completed it, the order shows the payment status
"refund pending" and a :guilabel:`Complete refund` action finishes it once the money is returned.

An :guilabel:`Export order` section appears when an order exporter is installed, with one button per
exporter that applies to that order; choosing one downloads the order in that exporter's format. No
exporter ships with this extension, because what an order has to look like for an ERP, an accounting
system or a fulfilment partner is specific to the shop — so an integrator adds the one you need (see
:ref:`developer-api-order-export`). With none installed, the section is not shown.

Order status and low-stock notifications
-------------------------------------------

The customer gets an email whenever an order's status changes (e.g. "confirmed" → "shipped"),
controlled by `products.email.orderStatusChanged.enabled` (on by default). Separately, placing an
order that brings an article's or a stock-tracked product's stock at or below
`products.stock.lowStockThreshold` (default 5) sends a warning to `products.email.merchantRecipient`
so the merchant can restock in time — this is independent of the status-change email and needs no
extra opt-in beyond having a merchant recipient configured at all.

Exporting orders
-------------------

This extension does not ship an order export format itself; third-party extensions can add one
(CSV, a specific ERP's import format, a marketplace feed, ...) by registering against
:php:`GoldeneZeiten\Products\Core\Export\OrderExportRegistry`. See
:ref:`Order export <introduction-order-export>` in the introduction for the technical extension
point.

..  _users-manual-withdrawal:

Order withdrawal
================

A customer can withdraw from their order themselves online - there is no fe_user account
requirement, matching this shop's guest-checkout-first design. The function follows Art. 9 to 14 of
the Consumer Rights Directive 2011/83/EU, including Art. 11a (inserted by Directive (EU) 2023/2673,
applicable since 19 June 2026): a clearly labelled "Withdraw from contract here" entry point
(German: "Vertrag widerrufen") leading to a statement form with a confirmation button that is
labelled only "Confirm withdrawal" (German: "Widerruf bestätigen").

..  _users-manual-withdrawal-period:

The withdrawal period
---------------------

The period is 14 days (`products.checkout.withdrawalPeriodDays`) and is worked out per order item:

*   **Goods.** The 14 days start the day after the customer - or a third party they named, other
    than the carrier - received the goods. For an order delivered in several parts, the **last**
    part counts. The delivery date comes from, in this order:

    *   the order's :guilabel:`Delivered at` date (``delivered_at``), which is set by the order
        module's :guilabel:`Mark delivered` button, by completing the order, or by an integration
        that learns of the delivery; an item can carry a delivery date of its own for split
        deliveries, and the order's period then starts with the latest one;
    *   otherwise the shipping date (read from the status log) plus the days in
        `products.withdrawal.assumedDeliveryDays` (14 by default - deliberately in the customer's
        favour).

    Until the goods are delivered, the period has not started: **an order can be withdrawn from
    before it is delivered, however old it is.** A shipped or delivered order stays withdrawable
    until the period has run out.

*   **Digital content** without a tangible medium: 14 days from the order. Once the customer gave
    the :ref:`waiver <users-manual-withdrawal-exemptions>` there is no right of withdrawal left.

*   **Withdrawal information missing.** If the withdrawal information never reached the customer on
    a durable medium - the order confirmation email carries it, and the order records when it was
    sent (``withdrawal_information_provided_at``) - the period is extended by twelve months
    (Art. 10). If the information arrives later, the period ends 14 days after it.

*   **Timeliness.** A withdrawal is in time when it was *submitted* before the period expired
    (Art. 11a(5)); the submission date and time is stored with the statement
    (``withdrawn_at``), so a statement that the shop processes later still counts.

Setting `products.checkout.withdrawalPeriodDays` to ``0`` disables the whole feature.

The withdrawal function
-----------------------

The form is reachable in three ways:

*   **Prefilled, via an order link.** The order confirmation email carries a prominent withdrawal
    button, and the thank-you and order-detail pages carry the same link. These links transport
    the order uid plus an HMAC capability token, so the form opens prefilled with the customer's
    name, the order number and the billing email - and the token, not the guessable uid, is what
    grants the prefill.

*   **Logged in.** A logged-in customer who opens the withdrawal page sees a list of their own
    withdrawable orders and picks one - they do not identify themselves again. Submitting is only
    accepted as a ``POST`` request from the logged-in session.

*   **Plain, via the withdrawal page itself** - typically linked from a footer or meta navigation
    (see :ref:`the page type below <users-manual-withdrawal-page-type>`). The customer enters
    their name, the order number and the billing email address and continues to a second step
    that lists the order's items. Unknown number and wrong email answer with the same generic
    message, so order numbers cannot be probed, and attempts are rate limited per client IP
    (``$GLOBALS['TYPO3_CONF_VARS']['SYS']['rateLimiter']['products-withdrawal-confirm']['limit']``,
    10 per minute by default).

The statement asks for the customer's name, shows the order number, asks to confirm the email
address the acknowledgement goes to and lists the order's items. All withdrawable items are
selected; the customer can deselect some to **withdraw from part of the order**. Items that carry
no right of withdrawal (:ref:`exempt <users-manual-withdrawal-exemptions>`), items already
withdrawn and items whose period has run out are shown but cannot be selected. Submitting checks
that the email matches the order's billing address, that the order is not cancelled and that at
least one selected item can still be withdrawn.

What happens after a withdrawal
-------------------------------

*   **The order had not been shipped.** The withdrawn items go back into stock. If nothing is left
    in the order it is moved to :guilabel:`Cancelled`; if items remain (for example an exempt
    personalized item that is still produced and shipped) it stays as it is.

*   **The order had been shipped or delivered.** The statement is recorded and the order moves to
    :guilabel:`Withdrawn, awaiting return`. Nothing goes back into stock until the returned goods
    are confirmed in the backend.

In both cases the customer immediately receives an **acknowledgement of receipt by email** (a
durable medium, Art. 11a(4)) with the content of the statement (name, order number, withdrawn
items, email address), the **date and time of its submission** in the shop's time zone
(`products.withdrawal.timezone`), the return instructions (address, deadline of 14 days, who bears the
cost), the refund rules and the model withdrawal form. The merchant is notified as well. The
order's status-changed email is not sent additionally.

Backend steps
-------------

The order module (:guilabel:`Orders`) shows the delivery, the withdrawal, the **date the refund is
due** (14 days after the statement, Art. 13) and the return milestones, and offers these actions:

*   :guilabel:`Mark delivered` - for a shipped order, stamps the delivery that starts the
    withdrawal period. Completing an order does the same when no delivery date was recorded.
*   :guilabel:`Record proof of return shipment` - for an order awaiting return, when the customer
    proved they sent the goods back.
*   :guilabel:`Goods received back` - the returned goods have arrived. The withdrawn items go back
    into stock; the order is completed when items remain with the customer and cancelled when
    everything came back.
*   :guilabel:`Refund` - refunds the payment through its payment method. After a partial withdrawal
    it covers the withdrawn lines only. For shipped goods the refund is **withheld until the goods
    are received back or proof of sending is recorded**, whichever comes first (Art. 13(3)); the
    module explains why the button is missing.

The order statuses and the moves between them:

*   New: can move to Pending, Confirmed or Cancelled.
*   Pending: can move to Confirmed or Cancelled.
*   Confirmed: can move to Shipped or Cancelled.
*   Shipped: can move to Completed, or - through a withdrawal - to Withdrawn, awaiting return.
*   Completed: can move to Withdrawn, awaiting return (a late withdrawal inside the period).
*   Withdrawn, awaiting return: can move to Completed or Cancelled; the backend does this with
    :guilabel:`Goods received back`.
*   Cancelled: final.

The withdrawn status is entered by the customer's withdrawal only; the backend does not offer it
as a manual step.

..  _users-manual-withdrawal-information:

Withdrawal instructions and model form
--------------------------------------

The trader has to give the model instructions on withdrawal and the model withdrawal form
(Annex I(A) and I(B) of the directive). They are rendered in the official English and German wording
with the trader's identity from the site settings (`products.withdrawal.trader.*`), and with the
start of the period that fits the order. They appear

*   on the withdrawal page, below the form,
*   in the order confirmation email (HTML and text), which is the confirmation on a durable medium
    that Art. 8(7) requires,
*   in the withdrawal acknowledgement email (the model form), and
*   anywhere an editor places the :rst:dir:`WithdrawalInstructions` content element, for example on
    the terms page. Without an order the start of the period follows
    `products.withdrawal.instructionVariant`.

Set the trader name, address, email and telephone number and, if returns go somewhere else, the
return address. A visible placeholder is printed until the trader name or address is configured.

..  _users-manual-withdrawal-page-type:

The withdrawal page type
--------------------------

Rather than placing the :rst:dir:`Withdrawal` plugin by hand, create a page of type
:guilabel:`Withdrawal page` (doktype ``175``): the plugin content element is inserted
automatically when the page is created (or an existing page is switched to the type), so the page
renders the form with no further editor steps. Put the page into your footer or meta navigation so
the function stays continuously available during the withdrawal period, as Art. 11a requires.
Title the page "Withdraw from contract here" (German: "Vertrag widerrufen"), the words Art. 11a
prescribes for the function. The
page record carries a :guilabel:`Navigation item CSS class(es)` field
(``pages.tx_products_nav_class``) to style that navigation entry, for example:

..  code-block:: typoscript

    page.20 = HMENU
    page.20.special = list
    page.20.special.value = <your withdrawal page uid>
    page.20.1 = TMENU
    page.20.1.NO.ATagParams.dataWrap = class="{field:tx_products_nav_class}"

..  _users-manual-withdrawal-exemptions:

Withdrawal exemptions (Art. 16)
---------------------------------

Products can be flagged in :guilabel:`Withdrawal right exemption` on the product record:

*   **Personalized / made to customer specifications** (Art. 16(c)) — engraved jewelry and other
    made-to-order goods. The right of withdrawal never arises for these items; the checkout review
    step shows the corresponding pre-contract notice.

*   **Digital content without tangible medium** (Art. 16(m)) — downloads. The checkout review step
    requires an additional express-consent checkbox (immediate supply plus the acknowledgment of
    losing the withdrawal right); the consent timestamp is stored on the order
    (``withdrawal_waiver_accepted_at``), and without it an order containing digital content cannot
    be placed through the normal checkout. Express/wallet checkouts collect no such consent, so
    orders placed through them simply keep their withdrawal right.

*   **Sealed goods unsuitable for return for health or hygiene reasons** (Art. 16(e)) and
    **sealed audio or video recordings and sealed software** (Art. 16(i)). The right is lost only
    if the customer unsealed the goods after delivery, which the shop cannot know - so these items
    **stay withdrawable**. The withdrawal form, the acknowledgement and the instructions state the
    condition instead.

The exemption is snapshotted onto every order item at placement time, so a later product edit does
not change the legal status an item was sold under. An order consisting entirely of exempt items
offers no withdrawal function — the email button and page links are replaced by an explanatory
notice, and the form refuses it. In a mixed order the exempt items are shown in the form but cannot
be selected, so the customer withdraws from the other items only; the merchant withdrawal
notification lists the exempt items.

The FE order-detail page (:rst:dir:`OrderHistory`'s ``show`` action, linked from the thank-you page
and order history) works the same way as invoice download and withdrawal: a logged-in customer
viewing their own order needs nothing extra, but a guest order (no fe_user account, matching this
shop's guest-checkout-first design) can only be opened with the matching security token carried in
its own "View Order" link — the plain order number/uid alone is never enough. This is deliberate: a
guest order's account association is empty, the same as any anonymous visitor's, so the token is
what actually proves the visitor is the order's rightful owner.

..  _users-manual-category-permissions:

Category permissions
======================

Beyond the coarse-grained :ref:`category mounts <configuration-backend-module>` (which subtree a
user/group can see at all), each category has an :guilabel:`Access` tab — the same owner-user/
owner-group/everybody permission model pages use, with Show/Edit/Delete/New Subcategories
checkboxes per owner. This is *additive* to mounts: a category outside a user's mounts is invisible
regardless of its permissions, and a category with permissions denying "Edit" cannot be changed by
that user even though they can see it. The "Delete" bit is enforced independently of "Edit" — a
user granted Edit but not Delete on a category can change it but not remove it, the same
show/edit/delete/new distinction TYPO3 pages themselves make.

Leaving all :guilabel:`Access` checkboxes empty on a category means "no one but an admin can edit
it" — set at least :guilabel:`Everybody` → :guilabel:`Edit` to keep the previous mounts-only
behaviour where any user with mount access could also edit.

..  _users-manual-vouchers:

Voucher codes
==============

Create vouchers as plain records (storage folder record list, or the record list view of the
:guilabel:`Products` module) with a :guilabel:`Code`, a :guilabel:`Discount type` (percentage or
fixed amount) and a :guilabel:`Discount value`. Shoppers enter a code in the basket; if it applies,
the discount shows there and carries through checkout.

*   :guilabel:`Combinable with other vouchers` — off by default. A non-combinable voucher always
    applies alone: entering it removes any other codes already applied, and it cannot be added
    alongside an existing one either. Combinable vouchers stack with each other freely. This also
    covers non-code discounts: a non-combinable voucher is rejected outright (not just cleared
    against other vouchers) when the basket is already reduced by a
    :ref:`category-cascading <users-manual-category-discounts>` or
    :ref:`FE-usergroup <users-manual-usergroup-discounts>` discount.
*   :guilabel:`Usage limit` — the total number of times the code can be redeemed across all
    customers, 0 for unlimited. Once reached, the code stops working for everyone.
*   :guilabel:`Minimum basket value` — the code is rejected below this basket total; 0 means no
    minimum.
*   :guilabel:`Bound to customer` — restricts the code to one specific customer account; leave
    empty for a public code anyone can use.
*   :guilabel:`Valid from` / :guilabel:`Valid until` — the same start/end-time fields used
    everywhere else in TYPO3; outside this window the code behaves as if it doesn't exist.
*   A discount can never exceed the basket's total, so applying a voucher never makes the amount
    due negative.
*   :guilabel:`Waives the shipping cost` — when checked and shipping costs are enabled (see
    :ref:`Shipping costs <users-manual-shipping>`), applying this code zeroes the shipping total
    regardless of which shipping method was chosen. It has no effect while shipping costs are
    disabled sitewide.

A code that becomes invalid while just viewing the basket (expired, exhausted by someone else,
etc.) simply stops contributing to the discount shown there — remove it to add a different one.
If a code was still valid on the basket page but became invalid by the time the shopper places the
order (someone else exhausted it in the meantime, for example), the whole order placement fails
with an error message instead of silently placing the order at a different price; the shopper is
sent back to the review step to remove the code and try again.

Once an order is placed, the applied voucher code(s) and the total discount are stored on the
order and shown in the backend order module's detail view, alongside a redemption record per
code (visible in the :guilabel:`Voucher redemption` record list) that keeps counting towards the
code's usage limit even if the order is later cancelled.

..  _users-manual-gained-vouchers:

Gained bonus vouchers
=======================

Beyond codes you create yourself, the shop can automatically reward customers with a fresh code
of their own. Enable it under :guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Products`
(:guilabel:`Auto-issue a reward voucher for qualifying orders`) — off by default, so existing shops
see no change until an operator opts in.

*   :guilabel:`Minimum order total (gross) to trigger a gained voucher` — an order at or above this
    total generates one reward code once it is placed; smaller orders generate nothing.
*   :guilabel:`Gained voucher discount type` / :guilabel:`Gained voucher discount value` — one
    sitewide reward, e.g. a flat 5.00 off (the default) or a percentage, applied the same way any
    other voucher discount is.

A generated code is non-combinable and single-use, bound to the customer who earned it when they
were logged in (open to anyone if the order was a guest checkout), and never expires. It shows up
like any other voucher in the storage folder's record list, and the order that triggered it is
recorded so the backend order module's detail view can show which code (if any) an order
generated and whether it has been used yet.

..  _users-manual-credit-points:

Credit points
==============

Credit points are a loyalty mechanic: customers earn points for products they buy and can later
spend points for a discount at checkout. The feature is off by default — enable it under
:guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Products`
(:guilabel:`Credit points enabled`) before any of the below takes effect. Existing shops upgrading
to this version see no change in behaviour until an operator opts in.

*   :guilabel:`Credit points earned per unit` — a plain field on each product, 0 by default. An
    article always earns at its product's rate; there is no separate per-article rate. This is used
    directly in the default `products.creditPoints.earningMode` (``perProduct``): each basket line
    earns its product's rate times quantity, summed across the whole order.
*   :guilabel:`Money value of one credit point when redeemed` — one sitewide setting (default
    0.10), separate from the per-product earning rate. Earning answers "how many points does
    buying this give you"; this setting answers "what is one point worth when spent" — keeping
    it sitewide means the discount a point is worth is predictable everywhere in the shop.

Setting `products.creditPoints.earningMode` to ``basketTiered`` switches earning to a flat,
order-level reward instead: `products.creditPoints.earningTiers` defines a list of
:samp:`{amount}:{points}` thresholds (e.g. ``50.00:10``), and an order earns whichever threshold's
points is the highest one at or below the order's total — a single flat award for the whole order,
not summed per line, unlike ``perProduct`` mode.

A customer's points balance is never stored directly — it is always the sum of their ledger
entries (visible in the :guilabel:`Credit Points Transaction` record list), the same
race-free approach used for voucher usage limits. Only identified customers have a ledger; guest
orders neither earn nor spend points, since there is no durable identity to credit.

At checkout, a logged-in customer with a positive balance sees a "spend credit points" field on
the review step. The requested amount is capped by whichever is lower: the customer's balance, or
what the current basket total can actually absorb at the redemption rate (so the payable amount
never goes negative) — the same double-cap idea used by the legacy shop. Placing the order records
one ledger entry for points earned on that order and, if any were spent, one entry for the points
redeemed; both discounts (voucher and credit points) are combined into a single discount total
shown on the order.

Manual adjustments
--------------------

For goodwill grants or corrections outside the normal earn/redeem flow, create a
:guilabel:`Credit Points Transaction` record directly (storage folder record list) with
:guilabel:`Type` set to :guilabel:`Manual adjustment`, the customer, and a :guilabel:`Points`
value — positive to grant points, negative to deduct them. Leave :guilabel:`Order UID` at 0 for an
adjustment not tied to a specific order. There is no separate approval step: any editor who can
open that record list can adjust any customer's balance, the same trust level as editing any other
record in this extension's storage folder.

..  _users-manual-shipping:

Shipping costs
================

Off by default — enable it under :guilabel:`Admin Tools` → :guilabel:`Settings` →
:guilabel:`Products` (:guilabel:`Shipping cost calculation enabled`) before any of the below
applies; existing shops upgrading see no change in checkout until an operator opts in.

Create shipping methods as plain records (storage folder record list) with a :guilabel:`Title`,
a :guilabel:`Country`, and a :guilabel:`Rate`:

*   :guilabel:`Country` — a specific country, or the fallback option (all countries) if no
    country-specific method is configured for the shopper's delivery country. If any
    country-specific methods exist for a country, the fallback methods are not offered there at
    all — configure a full set per country, or rely entirely on the fallback.
*   :guilabel:`Minimum/maximum order value` and :guilabel:`Minimum/maximum weight in grams` — leave
    at 0 for "no bound". A method is only offered when the basket's weight (the sum of each
    product's :guilabel:`Weight` field times quantity — articles use their product's weight, there
    is no per-article override) and goods total both fall inside its configured bounds.

When enabled, checkout gains a shipping-method step between the address and payment steps; the
chosen method's cost is added to the order total (shown separately from the goods total and any
voucher discount) unless a free-shipping voucher waives it. Checking a shipping method's
:guilabel:`Override the tax rate for this shipping method` box and filling in :guilabel:`Tax rate
override` makes the shipping line use that rate for its own tax bucket instead of deriving it from
the order's goods tax class(es) — useful where shipping is taxed differently from the goods
themselves. Any FE-usergroup discount the shopper qualifies for also
applies to the resolved shipping/handling cost, the same rate used for goods (category discounts
do not apply here, since neither shipping nor handling is tied to a product's category).

Bulky surcharge
-----------------

A product or article can be flagged :guilabel:`Bulky` (e.g. furniture, anything oversized). When
`products.shipping.bulkySurcharge` is set above ``0.00``, that flat amount is added once per bulky
item unit in the basket, on top of the chosen shipping method's rate — and survives a
free-shipping voucher, since an oversized item still costs extra to handle regardless of who pays
the base shipping rate.

Shipping classes
-----------------

A product can be assigned a :guilabel:`Shipping class` (on the :guilabel:`Shipping` tab), restricting
which carriers may ship it. The extension defines four options: leave it empty for no restriction,
or select :guilabel:`Hazardous goods`, :guilabel:`Freight only`, or :guilabel:`Refrigerated`.
The extension itself never interprets these values — each carrier decides which classes it is
willing to carry and refuses a basket it cannot serve.

**Important:** if a basket contains a product whose shipping class no installed carrier will carry,
no shipping option is offered and the customer cannot complete checkout — they are shown an error at
the shipping step instead. This is deliberate: an order nobody can deliver must not be payable. Do
not set a shipping class unless at least one carrier that handles it is installed. The built-in
table-rate carrier (shipping-method records) does not filter by shipping class.

Basket/checkout notices
--------------------------

Two independent, opt-in notices can be shown in the basket and checkout review:
`products.shipping.bulkyNoticeText` appears whenever the basket contains at least one bulky-flagged
item; `products.shipping.weightNoticeText` appears once the basket's total weight exceeds
`products.shipping.weightNoticeThreshold` grams. Both texts are empty by default, so existing shops
see nothing until an operator opts in by filling one in.

Handling fees
---------------

Separately from shipping, enable `products.handling.enabled` and create :guilabel:`Handling Fee`
records (storage folder record list) the same shape as shipping methods (:guilabel:`Country`,
:guilabel:`Rate`, min/max order value and weight bounds). Unlike shipping, a handling fee is never
shopper-chosen — the applicable one (if any) is resolved automatically and added to the order
total.

Deposits
----------

A product or article can carry a :guilabel:`Deposit` (e.g. a bottle/container deposit) — leaving
an article's deposit at ``0.00`` inherits the product's, the same "own value overrides, else fall
back" convention used for price and images. The deposit is shown as its own line in the basket and
added to the order total, separate from the goods total, shipping and any handling fee.

..  _users-manual-gift-orders:

Gift orders
============

On the checkout address step, a :guilabel:`Ship to a different address` checkbox reveals a second
address for delivery, plus a free-text :guilabel:`Gift message` field — both entirely optional.
Leaving the checkbox unchecked keeps the order billing-only, exactly as it worked before this
feature existed. When used, the delivery address and message are stored on the order and shown
alongside the billing address in checkout review, the thank-you page, order history, and the
backend order module.

This is one alternate delivery address for the whole order, not a per-item "send this one thing to
someone else" mechanic — a basket with several gifts for different people is out of scope for now.

A returning, logged-in customer who has not yet entered anything into the current checkout session
sees the address step pre-filled from their most recent order's billing address, so they don't have
to retype it every visit — this only ever pre-fills the initial, empty state, never overwrites an
address already entered in the current session. A first-time logged-in customer with no prior order
instead falls back to whatever address fields are already on their frontend user profile; a
brand-new customer with neither a prior order nor a filled-in profile simply sees a blank form, as
before.

..  _users-manual-wishlist:

Wishlist
=========

Enable the "add to wishlist" link on product listings under :guilabel:`Admin Tools` →
:guilabel:`Settings` → :guilabel:`Products` (:guilabel:`Show the add-to-wishlist affordance on
product listings`) — off by default. The :rst:dir:`Wishlist` plugin itself works regardless of
this setting; it only controls whether the link is injected into product listings, so a shop can
link to a wishlist page manually without necessarily surfacing it everywhere.

A logged-in customer's wishlist is stored against their account (visible as
:guilabel:`Wishlist Item` records) and follows them across visits and devices. A guest's wishlist
lives only in their browser session and is lost when it expires — logging in **merges** any
products from that session wishlist into the now-identified account's persisted one (deduplicated
against anything already there) and clears the session copy, so a guest who builds a wishlist
before creating an account or logging in does not lose it. If a wishlisted product is later
deleted, it simply disappears from the list.

Shoppers can reorder their own wishlist with move-up/move-down links next to each item — there is
no drag-and-drop, just swapping a product's position with its neighbour, one step at a time. This
works the same way for both a guest's session-based list and a logged-in customer's persisted one.

Placing an order automatically removes any of its items from the placing customer's wishlist
(guest orders are skipped, since a guest's wishlist is not tied to any identity an order could be
matched against) — a convenience cleanup that never blocks or fails the order itself if it runs
into trouble.

The wishlist page heading and the product-listing wishlist widget both show the current item
count. Enabling `products.session.requireCookieConsent` makes a guest's session wishlist (and the
:ref:`recently-viewed list <users-manual-recently-viewed>`) only get written to once the visitor's
browser already carries a confirmed session cookie from an earlier request — off by default, so
existing shops keep writing unconditionally until an operator opts in for stricter cookie-consent
compliance.

..  _users-manual-recently-viewed:

Recently-viewed products
==========================

The :rst:dir:`RecentlyViewed` plugin's ``list`` action shows the current visitor's most recently
viewed products, most recent first, automatically — there is nothing to configure per product.
Viewing a product already on the list moves it back to the front rather than showing it twice. It
lives entirely in the visitor's session (nothing is stored in the database, and nothing is tied to
their account even when logged in), so the list is lost when the session expires and never shared
across devices. :guilabel:`Maximum number of recently-viewed products remembered per visitor`
(default 10) caps how many products are kept.

..  _users-manual-most-viewed:

Most-viewed products
--------------------

The same :rst:dir:`RecentlyViewed` plugin also offers two persisted, cross-session/cross-device
listings, independent of the session-only recently-viewed one above: its ``mostViewed`` action
shows a site-wide "most viewed" ranking (every product view counts, from any visitor), and its
``myMostViewed`` action shows the current logged-in customer's own "your most viewed" ranking —
empty for anonymous visitors, since per-shopper view tracking only ever applies to an identified
account (no anonymous browsing behaviour is tracked beyond the session-only recently-viewed list).
`products.mostViewed.limit` (default 10) caps both listings independently of
`products.recentlyViewed.limit`.

..  _users-manual-invoice:

Invoice PDF
=============

Every order confirmation email carries a PDF invoice attachment, generated at send time from the
order's stored line items and addresses (no external program involved — pure-PHP rendering, so
there is nothing extra to install on the server). If attaching the PDF fails for any reason, the
confirmation email is still sent without it rather than being blocked entirely.

The same invoice is also reachable as a secured download link (shown in order history and usable
on its own, e.g. for a customer who deleted the original email) — the link embeds a signed token
tied to that specific order, so it cannot be guessed or reused for a different order.

Setting `products.email.agbAttachment` to a FAL combined file identifier (e.g.
``1:/legal/agb.pdf``) attaches that PDF (terms/AGB) to every order confirmation email alongside the
invoice — empty by default, so nothing is attached until an operator configures it. A missing or
misconfigured file never blocks the confirmation email itself; it is simply skipped.

..  _users-manual-category-notifications:

Category-level order notifications
=====================================

Beyond the sitewide `products.email.merchantRecipient`, a category can have its own
:guilabel:`Notification email` (and optional :guilabel:`Recipient name`) set on its edit form. When
an order contains a product from that category, the category's recipient gets the merchant
notification email in addition to the sitewide one — useful when different departments handle
different parts of the catalog (e.g. electronics vs. clothing). An order touching several such
categories notifies every one of them, deduplicated so the same address is never emailed twice for
the same order.

Independently of category routing, a product can also be assigned a :guilabel:`Shipping Point` —
a lightweight record with its own :guilabel:`Notification email`/:guilabel:`Recipient name` — for
shops that fulfil different products from different physical locations. An order touching one or
more shipping points notifies each one the same deduplicated way category recipients do, and both
routing axes (category and shipping point) fire independently and additively; a product can be
covered by neither, either, or both.
