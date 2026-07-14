<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Export;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The payload an order exporter produced, together with everything needed to hand it to the browser.
 */
#[Exclude]
final readonly class ExportResult
{
    public function __construct(
        private string $payload,
        private string $contentType,
        private string $fileName
    ) {}

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
