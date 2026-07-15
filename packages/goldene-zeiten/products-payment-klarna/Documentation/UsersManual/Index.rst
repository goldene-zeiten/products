:navigation-title: Users Manual

..  include:: /Includes.rst.txt
..  _users-manual:

=============
Users Manual
=============

This chapter is for editors and shop operators: what changes for the shopper at checkout once the
extension is installed and configured. See :ref:`Configuration <configuration>` for the technical
settings.

..  contents:: Table of contents
    :local:

..  _users-manual-checkout:

Klarna at checkout
====================

Once `products.payment.klarna.username` and `products.payment.klarna.password` are both set (see
:ref:`configuration`) and the order currency is one Klarna settles in, :guilabel:`Klarna` appears as a
payment option on the checkout payment step, alongside :guilabel:`Invoice`, PayPal, and any other
configured method. It has no fee (`calculateFee()` always returns 0) and is offered above invoice by
default, since it registers a higher priority.

If the credentials are not (yet) configured, or the order currency is one Klarna does not support, the
option simply does not appear — there is nothing for the shopper to see, and no broken payment attempt.

Paying with Klarna
=====================

#.  The shopper chooses :guilabel:`Klarna` and confirms the order. They are redirected to Klarna's
    Hosted Payment Page, where Klarna itself presents the payment options it offers for that
    shopper and amount — typically **pay now** (direct debit/card), **pay later** (invoice), and
    **pay in instalments**, depending on what Klarna approves for that customer and market.
#.  After authorizing, Klarna sends the shopper's browser back to the shop's checkout. The shop places
    the order with Klarna in the background and shows the usual order confirmation (thank-you page)
    once Klarna accepts it.
#.  If Klarna's fraud check only approves the payment conditionally, the order is left pending rather
    than paid or failed, until Klarna's own review resolves it.
#.  If the shopper cancels at Klarna instead of authorizing, or Klarna refuses the payment outright,
    they are sent back to the shop without a completed payment, and can choose a different payment
    method or try Klarna again.

Nothing about placing, viewing, or managing an order in the backend :guilabel:`Products` module changes
because of this extension — a Klarna order behaves like any other paid order once placed. Refunding or
cancelling a Klarna payment from the backend order module is not yet supported by this extension (see
:ref:`developer-future-refunds`); an operator handles a Klarna refund directly in the Klarna Merchant
Portal for now.

Reloading the confirmation page, or Klarna retrying its status callback in the background, never
double-charges the shopper or creates a duplicate order — both the browser return and the status
callback are safe to receive more than once for the same order.
