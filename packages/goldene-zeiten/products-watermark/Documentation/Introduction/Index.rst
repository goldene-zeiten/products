:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_watermark automatically watermarks product, article and category images shown by the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop, in the frontend only. It plugs
into TYPO3's own image processing rather than the catalog templates, so there is nothing to change in
Fluid or TypoScript to benefit from it.

..  contents:: Table of contents
    :local:

How it works
=============

:php:`GoldeneZeiten\Products\Watermark\EventListener\ApplyProductImageWatermarkListener` listens for
TYPO3 core's :php:`TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent`, which fires every time a
file is processed — including on a processing-cache hit. The listener only acts when
:php:`ProcessedFile::isUpdated()` reports that this call actually (re)generated the variant; that
cache-hit guard is what stops an already-watermarked variant from being watermarked again on every
request, and guarantees the watermark is only ever composited onto a distinct processed variant, never
onto the original file.

The watermark is composited with TYPO3's own :php:`TYPO3\CMS\Frontend\Imaging\GifBuilder`, so the
image processor configured for the installation (ImageMagick, GraphicsMagick or GD) is respected. The
watermarked result replaces the processed variant in place, so it is served both for the request that
generated it and from TYPO3's normal file-processing cache on every later request — the FAL original
is never modified.

Watermarking runs in the frontend only. In the backend and on CLI, file processing can be deferred, so
the processed file may not exist on disk yet at the point the event fires; the listener checks for a
frontend request before doing anything else, which both avoids inspecting a file that is not there yet
and means backend previews (e.g. the file list, or an image field's thumbnail in the record edit form)
are never watermarked.

Which images are watermarked
==============================

:php:`GoldeneZeiten\Products\Watermark\Resource\WatermarkableImageDetector` decides whether a
processed file's original is used as a product, article or category image, by checking
``sys_file_reference`` for a reference from one of:

*   the ``images`` field of ``tx_products_domain_model_product``
*   the ``images`` field of ``tx_products_domain_model_article``
*   the ``image`` field of ``tx_products_domain_model_category``

A product's or article's downloadable-file field uses a different field name and is therefore never
matched, so downloads are never watermarked. Images unrelated to the catalog (regular content-element
images, RTE images) are left untouched for the same reason.

Only the JPEG, PNG, GIF, WEBP and AVIF processed-image extensions are considered; every other
processing task type (e.g. text rendering) is ignored outright. Images narrower than
`products.watermark.minWidth` pixels are skipped as well, so small thumbnails are not watermarked.

Watermarking is disabled until a watermark image is configured — see
:ref:`Configuration <configuration>` — so installing the extension has no visible effect on its own.
A failure while compositing the watermark (a missing watermark file, or `GifBuilder` failing to
produce a result) is logged and leaves that image variant unwatermarked rather than breaking page
rendering.
