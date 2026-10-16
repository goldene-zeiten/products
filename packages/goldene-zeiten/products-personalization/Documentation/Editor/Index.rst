.. include:: /Includes.rst.txt

..  _editor:

================
Defining options
================

Open a product and switch to the tab :guilabel:`Personalization`. Each option has these fields:

:guilabel:`Kind`
    :guilabel:`Selection list` offers fixed choices, :guilabel:`Free text` a text field.

:guilabel:`Label`
    What the customer reads, in the form and on every document of the order. Translatable.

:guilabel:`Identifier`
    A short, unique name per product, e.g. ``size`` or ``engraving``. It identifies the option in
    baskets; do not change it while the option is in use.

:guilabel:`Required`
    The product cannot be added to the basket without this option.

:guilabel:`Makes the item personalized`
    Only for free texts: set it when the text makes the goods custom-made, such as an engraving text.

:guilabel:`Surcharge`
    Added per unit to the price, net or gross like the product prices. A choice of a selection list can
    carry a surcharge of its own on top.

:guilabel:`Maximum length` and :guilabel:`Allowed characters`
    Only for free texts. :guilabel:`Engravable` allows letters, digits, spaces and ``. , - & ' ! ? +``.

:guilabel:`Choices`
    Only for selection lists. Each choice has a label, a value that identifies it in baskets and orders,
    an optional surcharge and its own :guilabel:`Makes the item personalized` flag: set it for an engraving
    font, leave it off for "no engraving" or a regular size. Keep the value when you rename the label.

Options of articles
===================

An article has the same tab. Its options are added to those of the product; an option with the same
identifier as a product option replaces it, e.g. a longer engraving on a wider band.

Changing options
================

Baskets always show the current labels and surcharges. A basket line whose choice no longer exists, or
that lacks an option that became required, is removed from the basket. Placed orders keep the options as
they were sold.
