.. include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

The extension needs no settings. It renders its fields from the partial
:file:`EXT:products_personalization/Resources/Private/Partials/Product/LineItemOptions.html`, which
replaces the empty partial of the same name in the core add-to-basket forms through

..  code-block:: typoscript

    plugin.tx_productscore.view.partialRootPaths.130 = EXT:products_personalization/Resources/Private/Partials/

To change the markup, copy the partial into your site package and register its partial path with a
higher key. The field of an option must be named ``options[<identifier>]``; the ViewHelper
``{pp:options(product: product, article: article)}`` returns the options of the product and article.

The chosen options of a line are rendered by the core partial :file:`LineItem/Options` in the basket,
the checkout, the order mails and the invoice.
