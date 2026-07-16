:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14.3
*   PHP 8.2, 8.3, 8.4 or 8.5
*   `goldene-zeiten/products-core` and `goldene-zeiten/products-api-client` (installed automatically)
*   A Google Pay merchant (for PRODUCTION), a tokenization gateway, and a payment processor that can
    authorize the Google Pay token — see :ref:`configuration`

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-express-google-pay

Then:

#.  Activate the site set :guilabel:`Products Google Pay Express Checkout`
    (``goldene-zeiten/products-express-google-pay``) on every site that should offer the button.
#.  Configure the gateway and processor — see :ref:`Configuration <configuration>`. Start in
    :code:`TEST` while integrating; :code:`PRODUCTION` needs an approved Google Pay merchant and per-domain
    approval in the Google Pay & Wallet Console.
#.  Place the :guilabel:`Products: Google Pay Express Checkout` content element on the cart page.
