:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_voucher adds discount codes ("vouchers") to the EXT:products shop system: a shopper
enters a code on the basket page, and — if it applies — the discount is shown there and carries
through checkout. Beyond plain percentage/fixed-amount discounts, a voucher can also waive the
shipping cost entirely, and the shop can automatically reward a customer with a fresh code once
their order passes a threshold.

..  contents:: Table of contents
    :local:

Discount codes and free-shipping vouchers
==========================================

A voucher record (see :ref:`Editor <editor>`) carries a code, a discount type (percentage or fixed
amount) and a discount value. A separate :guilabel:`Waives the shipping cost` flag turns a voucher
into a free-shipping voucher: applying it does not change the discount value calculation, but it
additionally offsets whatever the chosen shipping method would otherwise have cost. Vouchers can be
combinable or single-use-only, bound to one customer or public, limited in how many times they may
be redeemed in total, and restricted to a minimum basket value and a validity window.

Beyond codes an editor creates manually, the shop can auto-issue a "gained" voucher: once an order's
total reaches a configured threshold, the customer is rewarded with a fresh, single-use, non-
combinable code of their own. See :ref:`Gained bonus vouchers <editor-gained-vouchers>`.

..  _introduction-discount-seam:

Reached only through the core's discount seam
================================================

EXT:products (core) has no built-in concept of vouchers. It defines a generic discount contract —
:php:`GoldeneZeiten\Products\Core\Discount\DiscountProviderInterface` — that any extension can
implement to lower what the customer pays; the core's :php:`DiscountRegistry` collects every tagged
implementation and asks each one to compute (and, once an order is placed, book) a discount. This
extension's :php:`GoldeneZeiten\Products\Voucher\Discount\VoucherDiscountProvider` is the only piece
that reaches into that seam — the core itself stays unaware that a voucher programme exists at all.
See :ref:`Developer <developer>` for the full contract.

The codes a shopper enters are not carried on the basket DTO either. They live in the voucher
feature's own slice of session storage
(:php:`GoldeneZeiten\Products\Voucher\Discount\VoucherCheckoutState`), which the discount provider
reads directly off the request passed to it in :php:`DiscountContext`. This keeps the basket clean of
anything voucher-specific and lets the feature evolve — or be uninstalled — without touching the
core.

..  _introduction-basket-ui:

The voucher form rides the core basket plugin
================================================

The voucher-code form shown on the basket page is not its own content element. Only a plugin
actually placed on a page is dispatched, and the voucher form has to post back to whichever page the
core :guilabel:`Basket` plugin is placed on — so this extension registers its
:php:`GoldeneZeiten\Products\Voucher\Controller\VoucherController` (:php:`apply()`/:php:`remove()`
actions) onto the core's own :guilabel:`ProductsCore` extension / :guilabel:`Basket` plugin via
:php:`ExtensionUtility::registerControllerActions()` in :file:`ext_localconf.php`. No new CType is
introduced, and the basket content element itself is unchanged.

The core basket template ships a no-op "Discount/VoucherBasket" partial stub, rendering nothing by
default. This extension overrides that stub by adding its own partial path at a higher priority
(:file:`Configuration/TypoScript/setup.typoscript`,
``plugin.tx_productscore.view.partialRootPaths.120``) — so the voucher control only appears on the
basket page once this extension is actually installed.

..  _introduction-redemptions:

Redemptions link a code to an order
======================================

Every time a voucher is actually redeemed as part of a placed order, a row is written to
``tx_products_domain_model_voucherredemption`` — the durable code-to-order link, holding the voucher
UID/code, the order UID, the customer, the discount amount actually applied, and a timestamp. This
happens inside :php:`VoucherDiscountProvider::apply()`, which runs inside the order transaction, so a
failure there rolls the whole order placement back rather than leaving a half-redeemed voucher. See
:ref:`Editor <editor>` for where these redemptions surface in the backend, and
:ref:`Developer <developer>` for the full apply-phase mechanics.
