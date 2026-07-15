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

Stripe at checkout
====================

Once `products.payment.stripe.secretKey` is set (see :ref:`configuration`) and the order has a
currency, :guilabel:`Credit card (Stripe)` appears as a payment option on the checkout payment step,
alongside :guilabel:`Invoice` and any other configured method. It has no fee (`calculateFee()` always
returns 0) and is offered above invoice by default, since it registers a higher priority.

If the secret key is not (yet) configured, the option simply does not appear — there is nothing for the
shopper to see, and no broken payment attempt.

Paying with Stripe
=====================

#.  The shopper chooses :guilabel:`Credit card (Stripe)` and confirms the order. They are redirected to
    Stripe's hosted checkout page.
#.  On Stripe's checkout page, the shopper sees **card** as a payment option, and — wherever their
    device/browser supports it and the shop's domain is registered (see
    :ref:`configuration-payment-method-domains`) — **Apple Pay**, **Google Pay** and **Wero** as
    one-tap alternatives. Which of these actually shows up is entirely decided by Stripe and the
    shopper's device; there is nothing to configure per order.
#.  After paying, Stripe sends the shopper's browser back to the shop's checkout. The shop confirms the
    payment with Stripe in the background and shows the usual order confirmation (thank-you page) once
    the session is confirmed paid.
#.  If the shopper leaves Stripe's checkout without paying, they are sent back to the shop without a
    completed payment, and can choose a different payment method or try Stripe again.

Nothing about placing, viewing, or managing an order in the backend :guilabel:`Products` module changes
because of this extension — a Stripe order behaves like any other paid order once confirmed. Refunding
or cancelling a Stripe payment from the backend order module is not yet supported by this extension (see
:ref:`developer-future-refunds`); an operator handles a Stripe refund directly in the Stripe Dashboard for
now.

Reloading the confirmation page, or Stripe retrying its webhook in the background, never double-charges
the shopper or creates a duplicate payment — both the browser return and the webhook confirmation are
safe to receive more than once for the same order.
