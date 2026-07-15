<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Exception;

/**
 * Thrown when a watermark cannot be composited onto a processed image. The listener catches it and
 * leaves the image untouched, so a misconfiguration never breaks frontend rendering.
 */
final class WatermarkApplicationException extends \RuntimeException {}
