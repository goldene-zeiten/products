:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-site-set:

Site set
========

Activate the :guilabel:`Products Watermark` site set (``goldene-zeiten/products-watermark``) on every
site that should watermark its catalog images, then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings` → :guilabel:`Products` → :guilabel:`Watermark`.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.watermark.file
        :type: string
        :Default: (empty)

        Path to the watermark image, e.g. ``EXT:my_sitepackage/Resources/Public/Images/watermark.png``
        or a fileadmin path. A PNG with transparency is recommended. Watermarking as a whole is
        disabled while this is empty — this is the only setting that turns the feature on.

    ..  confval:: products.watermark.position
        :type: string
        :Default: bottom-right

        Where the watermark is placed on the image: ``top-left``, ``top-right``, ``bottom-left``,
        ``bottom-right``, ``center`` or ``tile`` (repeated across the whole image). An unrecognised
        value falls back to ``bottom-right`` rather than breaking image rendering.

    ..  confval:: products.watermark.opacity
        :type: int
        :Default: 50

        Opacity of the watermark in percent (``0`` fully transparent, ``100`` fully opaque).
        Multiplied with any transparency already present in the watermark image itself. Clamped to
        the 0–100 range.

    ..  confval:: products.watermark.scale
        :type: int
        :Default: 25

        Width of the watermark relative to the processed image's width, in percent. ``0`` keeps the
        watermark at its native pixel size instead of scaling it. For the ``tile`` position this is
        the size of a single tile. Clamped to the 0–100 range.

    ..  confval:: products.watermark.minWidth
        :type: int
        :Default: 200

        Processed images narrower than this many pixels are left untouched, so small thumbnails are
        never watermarked.

    ..  confval:: products.watermark.margin
        :type: int
        :Default: 5

        Distance of the watermark from the image edge for the four corner positions, in percent of
        the image width. Ignored for the ``center`` and ``tile`` positions. Clamped to the 0–100
        range.

..  _configuration-how-it-works:

How the automatic processing works
====================================

Watermarking needs no editor action per image: once `products.watermark.file` is set, every
product, article and category image the frontend serves is watermarked automatically the first time
each of its processed variants (a particular crop/scale) is generated, as described in
:ref:`Introduction <introduction>`. There is no per-record or per-image opt-out — the setting is
sitewide, and the relevant image field on the product, article or category record is what determines
whether an image is in scope at all.

..  note::

    Changing `products.watermark.file`, `products.watermark.position`, `products.watermark.opacity`,
    `products.watermark.scale` or `products.watermark.margin` only affects processed image variants
    generated *after* the change — already-watermarked variants sitting in TYPO3's file-processing
    cache are not regenerated automatically. Clear the file-processing cache (or the affected image's
    processed variants) after changing the watermark's appearance to see the new look everywhere.

..  _configuration-editor-behaviour:

What editors see
==================

Editors work with product, article and category images exactly as before — there is no watermark
field or preview to configure on the record itself, and the FAL originals shown in the backend (file
list, record edit forms) are never watermarked, since processing is deferred there and the listener
only ever acts on frontend requests. The watermark only appears in the rendered frontend output, on
images that come from the catalog's own image fields; a product's downloadable file field, and any
non-catalog image (content elements, RTE), is unaffected regardless of these settings.
