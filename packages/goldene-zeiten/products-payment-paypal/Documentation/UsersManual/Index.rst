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

PayPal at checkout
====================

Once `products.payment.paypal.clientId` and `products.payment.paypal.clientSecret` are both set
(see :ref:`configuration`) and the order currency is one PayPal settles in, :guilabel:`PayPal`
appears as a payment option on the checkout payment step, alongside :guilabel:`Invoice` and any
other configured method. It has no fee (`calculateFee()` always returns 0) and is offered above
invoice by default, since it registers a higher priority.

If the credentials are not (yet) configured, or the order currency is one PayPal does not support,
the option simply does not appear — there is nothing for the shopper to see, and no broken payment
attempt.

Paying with PayPal
=====================

#.  The shopper chooses :guilabel:`PayPal` and confirms the order. They are redirected to PayPal to
    log in (or check out as a PayPal guest) and approve the payment.
#.  After approving, PayPal sends the shopper's browser back to the shop's checkout. The shop
    captures the payment from PayPal in the background and shows the usual order confirmation
    (thank-you page) once the capture is confirmed.
#.  If the shopper cancels at PayPal instead of approving, they are sent back to the shop without a
    completed payment, and can choose a different payment method or try PayPal again.

Nothing about placing, viewing, or managing an order in the backend :guilabel:`Products` module
changes because of this extension — a PayPal order behaves like any other paid order once captured.
Refunding or cancelling a PayPal payment from the backend order module is not yet supported by this
extension (see :ref:`developer-future-refunds`); an operator handles a PayPal refund directly in the
PayPal account for now.

Reloading the confirmation page, or PayPal retrying its confirmation in the background, never
double-charges the shopper or creates a duplicate payment — both the browser return and the webhook
confirmation are safe to receive more than once for the same order.
