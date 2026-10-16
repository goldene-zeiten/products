.. include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

The extension adds three payment methods that need no payment provider:

:guilabel:`Prepayment (bank transfer)`
    The order waits for the money. The order confirmation names the amount, the account and the order
    number to quote as reference. A discount for paying in advance is optional.

:guilabel:`Cash on delivery`
    The carrier collects the amount on delivery, usually for a fee. It is not offered for deliveries
    without a carrier, such as pick-up in store.

:guilabel:`Cash on pick-up`
    The customer pays in store when collecting the order, so it is only offered with pick-up. A discount
    for paying cash is optional.

Every order paid offline is placed with the payment status :guilabel:`open`; mark it as paid in the
order module once the money has arrived. Discounts lower the price of the goods and are split across
their VAT rates, like a discount voucher; the cash-on-delivery fee carries the VAT of the shipping.
