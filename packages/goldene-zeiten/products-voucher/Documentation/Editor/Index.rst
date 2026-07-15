:navigation-title: Editor

..  include:: /Includes.rst.txt
..  _editor:

======
Editor
======

This chapter is for editors and shop operators: how to create a voucher record, what each of its
fields does, how a shopper actually redeems one, and where redeemed codes show up in the backend
order view.

..  contents:: Table of contents
    :local:

..  _editor-creating-vouchers:

Creating a voucher record
==========================

Create a :guilabel:`Voucher` record like any other plain record — in the storage folder's record
list, or the record list view of the :guilabel:`Products` backend module. Its fields are grouped into
palettes on the record's edit form:

**Identity**

..  confval:: code
    :type: string (input)

    The code the shopper types into the basket's voucher form (e.g. ``SUMMER20``). Required, and
    must be unique across every voucher record — the field is trimmed and enforced unique at the
    database level. This is also the record's own TCA label, so it is what identifies the voucher
    everywhere in the backend record list.

..  confval:: title
    :type: string (input)

    An internal-only title. Never shown to the shopper; purely a note for whoever manages vouchers
    in the backend (shown as the record list's secondary/subtitle label).

**Discount**

..  confval:: discount_type
    :type: select (percentage / fixed amount)

    Either :guilabel:`Percentage` or :guilabel:`Fixed amount`. Percentage discounts are computed
    against the basket's goods total at the time the code is applied; a fixed amount is taken off
    directly. Either way, the discount can never exceed the basket total — applying a voucher never
    makes the amount due negative.

..  confval:: discount_value
    :type: decimal (number)

    The discount value, interpreted according to `discount_type` — e.g. ``20.00`` means 20% off for
    a percentage voucher, or 20.00 (in the shop's currency) off for a fixed-amount voucher.

**Limits**

..  confval:: usage_limit
    :type: int

    :Default: 0

    The total number of times this code may be redeemed across all customers, ``0`` for unlimited.
    Once the limit is reached, the code stops working for everyone — enforced with an atomic
    ``UPDATE ... WHERE`` guard at redemption time, so two shoppers racing to use the last remaining
    redemption cannot both succeed.

..  confval:: redemption_count
    :type: int (read-only)

    :Default: 0

    How many times the code has actually been redeemed so far. Maintained automatically by the
    extension (incremented on every successful redemption) — this field cannot be edited directly.
    It keeps counting even if an order that redeemed the code is later cancelled; there is no
    mechanism that gives a redemption back.

..  confval:: min_basket_value
    :type: decimal (number)

    :Default: 0.00

    The code is rejected below this basket total (gross); ``0.00`` means no minimum.

**Validity**

..  confval:: valid_from
    :type: datetime

    The code behaves as if it does not exist before this point in time. This reuses TYPO3's normal
    start-time mechanism (the field is wired as the table's ``starttime`` enable-column), the same
    convention used to schedule any other record's visibility.

..  confval:: valid_until
    :type: datetime

    The code behaves as if it does not exist after this point in time (wired as the table's
    ``endtime`` enable-column, mirroring `valid_from`).

**Flags**

..  confval:: combinable
    :type: bool (checkbox)

    :Default: false

    Off by default. A non-combinable voucher always applies alone: entering it removes any other
    codes already applied in the checkout state, and it is refused outright if the basket already
    carries a discount from an existing (non-combinable) voucher. Combinable vouchers stack freely
    with each other.

..  confval:: waives_shipping_cost
    :type: bool (checkbox)

    :Default: false

    When checked, applying this code — on top of its own percentage/fixed discount — additionally
    zeroes whatever the shopper's chosen shipping method would have cost. It has no effect on the
    shop's separate bulky-item surcharge, which still applies regardless of who pays the base
    shipping rate.

**Other fields (outside the palettes)**

..  confval:: bound_frontend_user
    :type: select (foreign_table: fe_users)

    :Default: 0 (empty = anyone)

    Restricts the code to one specific customer account. Leave empty for a public code any shopper
    can use.

..  confval:: generated_from_order
    :type: select (foreign_table: tx_products_domain_model_order, read-only)

    :Default: 0 (empty = manually created)

    Set automatically when this voucher was auto-issued as a reward for a specific order (see
    :ref:`Gained bonus vouchers <editor-gained-vouchers>`) — not editable by hand. A manually
    created voucher leaves this at ``0``.

..  _editor-applying-a-code:

How a shopper applies a code in the basket
=============================================

Once installed, the basket page shows a small voucher-code form (a text field plus an
:guilabel:`Apply` button) below the basket contents — added by this extension without changing the
basket's content element itself (see :ref:`Introduction <introduction-basket-ui>`).

*   Submitting a code checks it exists, is currently valid (validity window, usage limit not
    exceeded), applies to this customer (`bound_frontend_user`), and meets `min_basket_value`. An
    invalid code shows an error message and the basket is left unchanged.
*   A code that is not `combinable` clears any codes already applied before adding itself; adding a
    combinable code alongside an already-applied non-combinable one is refused instead — the
    existing voucher wins, since it was already showing a discount to the shopper.
*   A voucher is also refused if the basket already carries a discount from something other than a
    voucher (e.g. an FE-usergroup or category-cascading discount from the core shop) and the code
    being applied is not combinable.
*   Once accepted, the code is kept in the customer's session (not on the basket itself) for the
    rest of the checkout; the basket immediately shows the recalculated discount total and the
    resulting amount still due, plus a :guilabel:`Remove` link next to each applied code.
*   Removing a code simply drops it from the session — no re-validation needed, since removing
    something can never make the basket invalid.

A code that was valid while viewing the basket but becomes invalid by the time the shopper actually
places the order (someone else exhausted it in the meantime, for example) fails the whole order
placement with an error rather than silently placing the order at a different price — vouchers are
only re-validated for real, and only actually redeemed, at that point.

..  _editor-backend-panel:

Where redemptions show in the backend order panel
=====================================================

Opening an order in the backend :guilabel:`Products` → :guilabel:`Orders` module's detail view shows
a :guilabel:`Vouchers applied` panel (part of the order's :guilabel:`Discounts & rewards` area,
alongside anything other discount-contributing extensions add there):

*   A table of every code redeemed against that order, with the discount amount it contributed and
    the timestamp it was redeemed at.
*   If the order itself generated a gained bonus voucher, a line noting the generated code and
    whether it has been used yet (``used`` / ``not yet used``).

Nothing needs to be configured for this panel to appear — it shows up automatically once at least
one voucher was redeemed on the order, or the order generated a reward voucher, and is entirely
absent (no empty panel) for an order with neither. The underlying data is the
``tx_products_domain_model_voucherredemption`` record created at redemption time and, for a gained
voucher, the ``generated_from_order`` field on the reward's own voucher record — the same records
visible directly in the storage folder's plain record list, gathered here per-order for convenience.

..  _editor-gained-vouchers:

Gained bonus vouchers
========================

Beyond codes an editor creates, the shop can automatically reward a customer with a fresh code of
their own once an order qualifies. Enable `products.vouchers.gained.enabled` (off by default, see
:ref:`Configuration <configuration>`) and set a minimum order value and a reward type/value.

A generated code is created with `combinable` off and `usage_limit` set to ``1`` (single-use), bound
to the customer who placed the qualifying order when they were logged in (left unbound — usable by
anyone — for a guest checkout), and has no validity window, so it never expires on its own. It
appears like any other voucher record in the storage folder's list, with `generated_from_order`
pointing back at the order that earned it, and shows up in that order's backend panel (see
:ref:`above <editor-backend-panel>`) whether or not it has been redeemed yet. A failure to issue a
gained voucher (e.g. a code-generation collision) is logged but never blocks or rolls back the order
that triggered it.
