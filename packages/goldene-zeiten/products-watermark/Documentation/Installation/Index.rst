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
*   `goldene-zeiten/products-core` (the shop this extension watermarks images for)

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-watermark

Then activate the site set :guilabel:`Products Watermark` (``goldene-zeiten/products-watermark``) on
every site that should watermark its catalog images, and configure at least the watermark image as
described under :ref:`Configuration <configuration>` — watermarking stays disabled until a watermark
image is set.
