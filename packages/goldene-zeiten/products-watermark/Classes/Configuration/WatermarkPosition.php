<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Configuration;

/**
 * Where the watermark is placed on the processed image.
 */
enum WatermarkPosition: string
{
    case TopLeft = 'top-left';
    case TopRight = 'top-right';
    case BottomLeft = 'bottom-left';
    case BottomRight = 'bottom-right';
    case Center = 'center';
    case Tile = 'tile';

    /**
     * Resolves a raw site-setting value, falling back to the default when it does not name a known
     * position, so a typo in the settings never breaks image rendering.
     */
    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::BottomRight;
    }
}
