<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Configuration;

/**
 * Immutable, request-independent snapshot of the watermark settings resolved for a single site.
 *
 * Services never read the request or the site themselves; they receive this value object built by
 * {@see WatermarkConfigurationFactory}, which is the only place aware of the settings source.
 */
final readonly class WatermarkConfiguration
{
    public function __construct(
        public string $file,
        public WatermarkPosition $position,
        public int $opacity,
        public int $scale,
        public int $minWidth,
        public int $margin,
    ) {}

    /**
     * Watermarking runs only when a watermark image has been configured; an empty file path keeps
     * the whole feature inert, which is the shipped default.
     */
    public function isEnabled(): bool
    {
        return $this->file !== '';
    }
}
