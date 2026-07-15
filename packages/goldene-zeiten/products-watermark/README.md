# TYPO3 extension `products_watermark`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Automatic watermarking of product, article and category images for the
[Products](https://github.com/goldene-zeiten/products-core) shop system.

Once a watermark image is configured, it is composited onto every scaled/cropped
frontend variant of a product, article or category image. The originals in FAL
are never touched, and each size variant is watermarked once and then cached by
TYPO3's normal file-processing cache.

## How it works

A PSR-14 listener on TYPO3's `AfterFileProcessingEvent` overlays the configured
watermark onto freshly generated image variants, using TYPO3's own image
processing (`GifBuilder`), so the server-side image processor configured in the
install (ImageMagick / GraphicsMagick / GD) is respected. Only images that are
actually referenced by a product, article or category record are affected;
everything else on the site is left alone.

## Installation

```shell
composer require goldene-zeiten/products-watermark
```

Add the "Products Watermark" site set to your site and configure at least the
watermark image path in the site settings (Settings module → Products →
Watermark). Watermarking stays disabled until a watermark image is set.

## Settings

| Setting                       | Meaning                                                        |
|-------------------------------|---------------------------------------------------------------|
| `products.watermark.file`     | Path to the watermark image (`EXT:` or fileadmin). Empty = off |
| `products.watermark.position` | `top-left`, `top-right`, `bottom-left`, `bottom-right`, `center`, `tile` |
| `products.watermark.opacity`  | Opacity in percent (multiplied with the image's own alpha)     |
| `products.watermark.scale`    | Watermark width as percent of the image width (0 = native size) |
| `products.watermark.minWidth` | Skip images narrower than this many pixels                     |
| `products.watermark.margin`   | Edge distance for corner positions, percent of image width    |

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`
- The PHP GD extension (used by TYPO3's `GifBuilder`)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
