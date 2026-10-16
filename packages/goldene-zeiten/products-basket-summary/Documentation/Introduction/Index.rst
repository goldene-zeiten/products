.. include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

The page header of a shop usually shows a basket icon with the number of items in the basket.
Rendering that number on the server makes every page uncached. This extension instead renders an
empty badge into the cached page and lets a small script fill it in: it asks the endpoint
``/products/basket-summary`` for the visitor's item count and total and updates the badge. A
server-rendered variant exists for pages that are uncached anyway.
