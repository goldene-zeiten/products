.. include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

All settings are site settings of the set :guilabel:`Products Offline Payment`.

..  confval:: products.payment.prepayment.enabled
    :name: products-payment-prepayment-enabled
    :type: bool
    :Default: false

    Offers prepayment.

..  confval:: products.payment.prepayment.accountHolder
    :name: products-payment-prepayment-accountHolder
    :type: string
    :Default: (empty)

    Account holder shown in the payment instructions.

..  confval:: products.payment.prepayment.iban
    :name: products-payment-prepayment-iban
    :type: string
    :Default: (empty)

    IBAN of the account. Prepayment is only offered when it is set.

..  confval:: products.payment.prepayment.bic
    :name: products-payment-prepayment-bic
    :type: string
    :Default: (empty)

    BIC of the account, optional.

..  confval:: products.payment.prepayment.bankName
    :name: products-payment-prepayment-bankName
    :type: string
    :Default: (empty)

    Name of the bank, optional.

..  confval:: products.payment.prepayment.discountPercent
    :name: products-payment-prepayment-discountPercent
    :type: string
    :Default: 0

    Discount on the goods for paying in advance, e.g. ``2``.

..  confval:: products.payment.cashOnDelivery.enabled
    :name: products-payment-cashOnDelivery-enabled
    :type: bool
    :Default: false

    Offers cash on delivery.

..  confval:: products.payment.cashOnDelivery.fee
    :name: products-payment-cashOnDelivery-fee
    :type: string
    :Default: 0.00

    Gross fee, e.g. ``4.40``.

..  confval:: products.payment.cashOnDelivery.excludedShipping
    :name: products-payment-cashOnDelivery-excludedShipping
    :type: string
    :Default: pickup

    Shipping providers (e.g. ``pickup``) or single options (e.g. ``ups:express``), comma-separated, for which cash on delivery is not offered.

..  confval:: products.payment.cash.enabled
    :name: products-payment-cash-enabled
    :type: bool
    :Default: false

    Offers cash on pick-up.

..  confval:: products.payment.cash.shipping
    :name: products-payment-cash-shipping
    :type: string
    :Default: pickup

    Shipping providers or single options, comma-separated, that mean collecting in store. Cash is only offered with them.

..  confval:: products.payment.cash.discountPercent
    :name: products-payment-cash-discountPercent
    :type: string
    :Default: 0

    Discount on the goods for paying cash, e.g. ``3``.

Example
=======

..  code-block:: yaml

    products:
      payment:
        prepayment:
          enabled: true
          accountHolder: 'Goldschmiede Muster'
          iban: 'DE02 1203 0000 0000 2020 51'
        cashOnDelivery:
          enabled: true
          fee: '4.40'
        cash:
          enabled: true
          discountPercent: '3'
        invoice:
          excludedShipping: 'pickup'

The last line uses the core setting ``products.payment.invoice.excludedShipping`` to offer invoice only
for shipped orders.
