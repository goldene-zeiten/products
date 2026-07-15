:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-site-set:

Site set
========

Activate the :guilabel:`Products Voucher` site set (``goldene-zeiten/products-voucher``) on every
site that should offer voucher codes, then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings`. These settings only control the automatic
"gained voucher" reward — creating voucher codes yourself needs no configuration at all, see
:ref:`Editor <editor-creating-vouchers>`.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.vouchers.gained.enabled
        :type: bool
        :Default: false

        Whether placing a qualifying order automatically issues the customer a reward voucher. Off
        by default, so installing this extension changes nothing until an operator opts in. See
        :ref:`Gained bonus vouchers <editor-gained-vouchers>`.

    ..  confval:: products.vouchers.gained.minimumOrderValue
        :type: number
        :Default: 0.00

        Minimum order total (gross) required to trigger a gained voucher. ``0.00`` means every
        order qualifies once the feature is enabled.

    ..  confval:: products.vouchers.gained.rewardType
        :type: string
        :Default: fixed

        The discount type of an auto-issued gained voucher: either ``fixed`` or ``percentage`` —
        the same two discount types an editor can pick for any voucher record.

    ..  confval:: products.vouchers.gained.rewardValue
        :type: number
        :Default: 5.00

        The discount value of an auto-issued gained voucher, interpreted according to
        `products.vouchers.gained.rewardType`.

..  note::
    These settings live under the :guilabel:`Vouchers` category of the :guilabel:`Products` site
    settings group (:guilabel:`Site Management > Sites > Edit settings > Products > Vouchers`), next
    to the core shop's own settings.
