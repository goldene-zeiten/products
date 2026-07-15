..  _start:

==================
Products Watermark
==================

:Extension key:
    products_watermark

:Package name:
    goldene-zeiten/products-watermark

:Version:
    |release|

:Language:
    en

:Author:
    Markus Hofmann

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

----

Automatic watermarking of product, article and category images for the Products shop system.

----

How it works
============

A PSR-14 listener on TYPO3's ``AfterFileProcessingEvent`` composites the configured watermark onto
every freshly generated, scaled or cropped frontend variant of an image that is referenced by a
product, article or category record. The compositing is done through TYPO3's own image processing
(``GifBuilder``), so the image processor configured for the installation (ImageMagick,
GraphicsMagick or GD) is respected.

The FAL originals are never modified — only the processed variants are watermarked, and each
variant is watermarked exactly once and then served from TYPO3's normal file-processing cache.
Images that do not belong to a product, article or category (regular content-element images, RTE
images, backend previews) are left untouched, and watermarking only happens in the frontend.

Watermarking is disabled until a watermark image is configured, so installing the extension has no
visible effect on its own.

Installation
============

..  code-block:: bash

    composer require goldene-zeiten/products-watermark

Add the :guilabel:`Products Watermark` site set to your site, then configure at least the watermark
image in the site settings (:guilabel:`Settings` module → :guilabel:`Products` →
:guilabel:`Watermark`).

Settings
========

..  confval:: products.watermark.file
    :type: string
    :Default: (empty)

    Path to the watermark image, e.g. ``EXT:my_sitepackage/Resources/Public/Images/watermark.png``
    or a fileadmin path. A PNG with transparency is recommended. Leave empty to disable
    watermarking.

..  confval:: products.watermark.position
    :type: string
    :Default: bottom-right

    Where the watermark is placed: ``top-left``, ``top-right``, ``bottom-left``, ``bottom-right``,
    ``center`` or ``tile`` (repeated across the whole image).

..  confval:: products.watermark.opacity
    :type: int
    :Default: 50

    Opacity of the watermark in percent. Multiplied with any transparency already present in the
    watermark image.

..  confval:: products.watermark.scale
    :type: int
    :Default: 25

    Width of the watermark relative to the image width, in percent. Set to ``0`` to keep the
    watermark's native pixel size. For the ``tile`` position this is the size of a single tile.

..  confval:: products.watermark.minWidth
    :type: int
    :Default: 200

    Images narrower than this many pixels are left untouched, so small thumbnails do not get
    watermarked.

..  confval:: products.watermark.margin
    :type: int
    :Default: 5

    Distance of the watermark from the image edge for the corner positions, in percent of the image
    width. Ignored for the ``center`` and ``tile`` positions.
