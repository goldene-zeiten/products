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

Amazon Pay at checkout
======================

Once `products.payment.amazon.publicKeyId`, `products.payment.amazon.privateKey`,
`products.payment.amazon.storeId` and `products.payment.amazon.merchantStoreName` are all set (see
:ref:`configuration`) and the order currency is one Amazon Pay settles in, :guilabel:`Amazon Pay`
appears as a payment option on the checkout payment step, alongside :guilabel:`Invoice`, Klarna, PayPal,
and any other configured method. It has no fee (`calculateFee()` always returns 0) and is offered at the
configured priority (default 30, which places it above invoice but below other premium methods).

If any required credential is not configured, or the order currency is one Amazon Pay does not support,
the option simply does not appear — there is nothing for the shopper to see, and no broken payment
attempt.

Paying with Amazon Pay
=======================

#.  The shopper chooses :guilabel:`Amazon Pay` and confirms the order. They are redirected to Amazon's
    authentication page, where they sign in with their Amazon account (or create one).

#.  After signing in, they return to the shop's checkout page (:guilabel:`Review and Confirm`), where
    they can verify their shipping address and payment method. If the address was updated on Amazon's
    side, the order total may change (e.g. if shipping costs vary by region). The shopper reviews and
    then confirms to proceed with payment.

#.  Amazon sends the shopper's browser back to the shop a second time to complete the payment. The shop
    charges the payment method and shows the usual order confirmation (thank-you page) once Amazon
    accepts it.

#.  If Amazon's payment processor only approves the charge conditionally (e.g. for fraud review), the
    order is left pending rather than paid or failed, until Amazon's own review resolves it.

#.  If the shopper cancels on Amazon's pages instead of signing in or confirming, they are sent back to
    the shop without a completed payment, and can choose a different payment method or try Amazon Pay
    again.

Nothing about placing, viewing, or managing an order in the backend :guilabel:`Products` module changes
because of this extension — an Amazon-paid order behaves like any other paid order once placed. Refunding
or cancelling an Amazon payment from the backend order module is not yet supported by this extension; an
operator handles an Amazon refund directly in Seller Central for now.

Reloading the confirmation page, or Amazon retrying its status callback in the background, never
double-charges the shopper or creates a duplicate order — both the browser return (both legs) and the
status callback are safe to receive more than once for the same order.
