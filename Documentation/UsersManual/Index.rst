:navigation-title: Users Manual

..  include:: /Includes.rst.txt
..  _users-manual:

=============
Users Manual
=============

This chapter is for editors and shop operators: what you can do with the extension day to day, once
it is installed and configured. See :ref:`Configuration <configuration>` for technical settings.

..  contents:: Table of contents
    :local:

..  _users-manual-catalog:

Managing the catalog
=====================

Categories, products and articles are managed in the :guilabel:`Products` backend module (not in the
page tree list module, since these records are not organised by page). Open it via the main module
menu entry :guilabel:`Products`.

*   Use the tree on the left to navigate categories. Right-click (or the toolbar) offers
    :guilabel:`New category`, and dragging a category onto another re-parents it.
*   Selecting a category shows its products on the right; selecting a product shows its articles.
*   Use the :guilabel:`New product in this category` / :guilabel:`New article for this product`
    actions to create records in place; editing opens the normal TYPO3 record edit form.

Articles are purchasable variants of a product (e.g. a specific size or colour). A product with
articles can only be ordered via one of its articles — the product itself is then just the shared
catalog entry (title, description, categories); price, stock and EAN are set per article. A product
without any articles is directly purchasable itself.

..  _users-manual-media:

Product media: images and downloads
====================================

Products, articles and categories each have an :guilabel:`Images` (or, for categories, a single
:guilabel:`Image`) field, plus an optional :guilabel:`Downloads` field on products and articles for
attachments such as data sheets or manuals — filled in like any other file field in the TYPO3
backend (drag & drop, or the file browser).

*   The first image in a product's gallery is used as its teaser image in product listings; the full
    gallery is shown on the product detail page.
*   An article's own :guilabel:`Images` field is optional. Leave it empty to show the product's
    gallery instead (the same "leave empty to inherit" convention used for the article price) — only
    fill it in when a specific variant (e.g. a colour) needs its own pictures.
*   Downloads are shown as a plain file list on the product detail page; they carry no access
    restriction beyond the record's own visibility (hidden/start-end time).
