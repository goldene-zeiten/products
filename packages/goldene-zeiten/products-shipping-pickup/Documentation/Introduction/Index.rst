.. include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

Customers collect their order in one of the shop's branches instead of having it shipped. Every branch
is offered as a shipping option of its own, free of charge, named with its address, e.g.
:guilabel:`Pick-up at Hamburg (Jungfernstieg 1, 20354 Hamburg)`; its opening hours are shown as the
delivery estimate. The chosen branch is stored on the order and named in the order mails.

The shipping provider identifier is ``pickup``. Payment methods use it to depend on collection, e.g.
cash on pick-up of ``goldene-zeiten/products-payment-offline`` is only offered with it, and invoice can
be excluded through the core setting ``products.payment.invoice.excludedShipping``.
