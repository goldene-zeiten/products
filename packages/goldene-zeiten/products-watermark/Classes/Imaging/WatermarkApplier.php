<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Imaging;

use GoldeneZeiten\Products\Watermark\Configuration\WatermarkConfiguration;
use GoldeneZeiten\Products\Watermark\Configuration\WatermarkPosition;
use GoldeneZeiten\Products\Watermark\Exception\WatermarkApplicationException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Imaging\GifBuilder;

/**
 * Composites the configured watermark onto an already processed image variant using TYPO3's own
 * image processing ({@see GifBuilder}), so the install's configured image processor (ImageMagick /
 * GraphicsMagick / GD) is respected.
 *
 * The uniform opacity is baked into the watermark's alpha channel beforehand, because GifBuilder's
 * image-overlay object has no opacity parameter of its own (only TEXT and BOX objects do).
 */
final class WatermarkApplier
{
    private const CACHE_FOLDER = 'typo3temp/assets/products_watermark';

    /**
     * Returns the absolute path of a copy of $imagePath with the watermark applied.
     *
     * @param int $imageWidth Width of the processed image in pixels
     * @param int $imageHeight Height of the processed image in pixels
     */
    public function apply(string $imagePath, int $imageWidth, int $imageHeight, WatermarkConfiguration $config): string
    {
        $watermarkFile = $this->prepareWatermark($config);
        $format = $this->outputFormat($imagePath);
        $configuration = $this->buildConfiguration($imagePath, $imageWidth, $imageHeight, $watermarkFile, $format, $config);

        return $this->runGifBuilder($configuration);
    }

    /**
     * Resolves the watermark file and, when an opacity below 100 % is configured, returns a cached
     * copy whose alpha channel has been reduced accordingly.
     */
    private function prepareWatermark(WatermarkConfiguration $config): string
    {
        $sourcePath = GeneralUtility::getFileAbsFileName($config->file);
        if ($sourcePath === '' || !is_file($sourcePath)) {
            throw new WatermarkApplicationException(sprintf('Watermark image "%s" not found.', $config->file), 1752570001);
        }
        if ($config->opacity >= 100) {
            return $sourcePath;
        }

        $cachePath = $this->cacheFilePath($sourcePath, $config->opacity);
        if (!is_file($cachePath)) {
            $this->bakeOpacity($sourcePath, $cachePath, $config->opacity);
        }

        return $cachePath;
    }

    private function cacheFilePath(string $sourcePath, int $opacity): string
    {
        $key = md5($sourcePath . '|' . (string)filemtime($sourcePath) . '|' . $opacity);
        $folder = Environment::getPublicPath() . '/' . self::CACHE_FOLDER;
        GeneralUtility::mkdir_deep($folder);

        return $folder . '/wm_' . $key . '.png';
    }

    /**
     * Rescales every pixel's alpha by the opacity factor, preserving the watermark's own
     * transparency. This is inherently a per-pixel operation, hence the loop.
     */
    private function bakeOpacity(string $sourcePath, string $targetPath, int $opacity): void
    {
        $source = $this->loadGdImage($sourcePath);
        $width = max(1, imagesx($source));
        $height = max(1, imagesy($source));
        $target = imagecreatetruecolor($width, $height);
        if (!$target instanceof \GdImage) {
            throw new WatermarkApplicationException('Cannot allocate the watermark canvas.', 1752570005);
        }
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefilledrectangle($target, 0, 0, $width, $height, (int)imagecolorallocatealpha($target, 0, 0, 0, 127));
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $newAlpha = max(0, min(127, 127 - (int)round((127 - $alpha) * $opacity / 100)));
                $color = imagecolorallocatealpha($target, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF, $newAlpha);
                imagesetpixel($target, $x, $y, (int)$color);
            }
        }
        imagepng($target, $targetPath);
    }

    private function loadGdImage(string $path): \GdImage
    {
        $type = (int)(getimagesize($path)[2] ?? 0);
        $image = match ($type) {
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => false,
        };
        if (!$image instanceof \GdImage) {
            throw new WatermarkApplicationException(sprintf('Cannot read watermark image "%s".', $path), 1752570002);
        }

        return $image;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function buildConfiguration(string $imagePath, int $imageWidth, int $imageHeight, string $watermarkFile, string $format, WatermarkConfiguration $config): array
    {
        $configuration = [
            'XY' => $imageWidth . ',' . $imageHeight,
            'format' => $format,
            '10' => 'IMAGE',
            '10.' => ['file' => $imagePath],
            '20' => 'IMAGE',
            '20.' => array_merge(['file' => $watermarkFile], $this->placement($imageWidth, $imageHeight, $watermarkFile, $config)),
        ];
        if ($format === 'png') {
            $configuration['backColor'] = 'transparent';
        }

        return $configuration;
    }

    /**
     * Computes the GIFBUILDER IMAGE properties (scaling, offset, tiling) for the watermark overlay.
     *
     * @return array<string, mixed>
     */
    private function placement(int $imageWidth, int $imageHeight, string $watermarkFile, WatermarkConfiguration $config): array
    {
        [$nativeWidth, $nativeHeight] = $this->dimensions($watermarkFile);
        $targetWidth = $config->scale > 0 ? max(1, (int)round($imageWidth * $config->scale / 100)) : $nativeWidth;
        $targetHeight = max(1, (int)round($nativeHeight * $targetWidth / max(1, $nativeWidth)));
        $placement = $config->scale > 0 ? ['file.' => ['width' => $targetWidth, 'height' => $targetHeight]] : [];

        if ($config->position === WatermarkPosition::Tile) {
            return $placement + ['offset' => '0,0', 'tile' => $this->tileCount($imageWidth, $imageHeight, $targetWidth, $targetHeight)];
        }

        return $placement + ['offset' => $this->offset($imageWidth, $imageHeight, $targetWidth, $targetHeight, $config)];
    }

    private function offset(int $imageWidth, int $imageHeight, int $watermarkWidth, int $watermarkHeight, WatermarkConfiguration $config): string
    {
        $margin = (int)round($imageWidth * $config->margin / 100);
        $right = max(0, $imageWidth - $watermarkWidth - $margin);
        $bottom = max(0, $imageHeight - $watermarkHeight - $margin);
        $centerX = max(0, (int)round(($imageWidth - $watermarkWidth) / 2));
        $centerY = max(0, (int)round(($imageHeight - $watermarkHeight) / 2));

        return match ($config->position) {
            WatermarkPosition::TopLeft => $margin . ',' . $margin,
            WatermarkPosition::TopRight => $right . ',' . $margin,
            WatermarkPosition::BottomLeft => $margin . ',' . $bottom,
            WatermarkPosition::Center => $centerX . ',' . $centerY,
            default => $right . ',' . $bottom,
        };
    }

    private function tileCount(int $imageWidth, int $imageHeight, int $watermarkWidth, int $watermarkHeight): string
    {
        $columns = min(20, max(1, (int)ceil($imageWidth / max(1, $watermarkWidth))));
        $rows = min(20, max(1, (int)ceil($imageHeight / max(1, $watermarkHeight))));

        return $columns . ',' . $rows;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensions(string $path): array
    {
        $size = getimagesize($path);
        if ($size === false) {
            throw new WatermarkApplicationException(sprintf('Cannot read dimensions of watermark image "%s".', $path), 1752570003);
        }

        return [max(1, (int)$size[0]), max(1, (int)$size[1])];
    }

    private function outputFormat(string $imagePath): string
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    /**
     * @param array<int|string, mixed> $configuration
     */
    private function runGifBuilder(array $configuration): string
    {
        $gifBuilder = GeneralUtility::makeInstance(GifBuilder::class);
        $gifBuilder->start($configuration, []);
        $result = $gifBuilder->gifBuild();
        if ($result === null) {
            throw new WatermarkApplicationException('GifBuilder did not produce a watermarked image.', 1752570004);
        }

        return $result->getFullPath();
    }
}
