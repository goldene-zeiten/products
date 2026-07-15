<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\EventListener;

use GoldeneZeiten\Products\Watermark\Configuration\WatermarkConfiguration;
use GoldeneZeiten\Products\Watermark\Configuration\WatermarkConfigurationFactory;
use GoldeneZeiten\Products\Watermark\Imaging\WatermarkApplier;
use GoldeneZeiten\Products\Watermark\Resource\WatermarkableImageDetector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Watermarks freshly processed product, article and category image variants in the frontend.
 *
 * The watermark is applied on {@see AfterFileProcessingEvent}, which fires on every processing call,
 * including cache hits. Applying only when the processed file was actually (re)generated in this call
 * ({@see ProcessedFile::isUpdated()}) is what stops a cached variant from being watermarked again on
 * every request; it also guarantees we only ever touch a distinct processed variant, never the
 * original. `isUpdated()` is available on both TYPO3 13 and 14, unlike the removed `getTask()`.
 */
#[AsEventListener]
final class ApplyProductImageWatermarkListener
{
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    public function __construct(
        private readonly WatermarkableImageDetector $detector,
        private readonly WatermarkApplier $applier,
        private readonly WatermarkConfigurationFactory $configurationFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(AfterFileProcessingEvent $event): void
    {
        // The frontend check comes first: in the backend and on CLI, processing is deferred, so the
        // processed file is not on disk yet and must not be inspected. We watermark only in the
        // frontend anyway, so this both guards correctness and skips the work cheaply everywhere else.
        $site = $this->frontendSite();
        if (!$site instanceof Site) {
            return;
        }

        $processedFile = $event->getProcessedFile();
        if (!$this->isFreshlyProcessedImage($event, $processedFile)) {
            return;
        }

        $configuration = $this->configurationFactory->fromSite($site);
        if (!$configuration->isEnabled()) {
            return;
        }

        $this->watermark($processedFile, $configuration);
    }

    private function isFreshlyProcessedImage(AfterFileProcessingEvent $event, ProcessedFile $processedFile): bool
    {
        if ($event->getTaskType() !== ProcessedFile::CONTEXT_IMAGECROPSCALEMASK) {
            return false;
        }

        if (!$processedFile->isUpdated()) {
            return false;
        }

        return in_array(strtolower($processedFile->getExtension()), self::SUPPORTED_EXTENSIONS, true);
    }

    private function frontendSite(): ?Site
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface || !ApplicationType::fromRequest($request)->isFrontend()) {
            return null;
        }

        $site = $request->getAttribute('site');

        return $site instanceof Site ? $site : null;
    }

    private function watermark(ProcessedFile $processedFile, WatermarkConfiguration $configuration): void
    {
        if ((int)$processedFile->getProperty('width') < $configuration->minWidth) {
            return;
        }
        if (!$this->detector->isWatermarkable($processedFile->getOriginalFile()->getUid())) {
            return;
        }

        try {
            $this->applyWatermark($processedFile, $configuration);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to apply product image watermark.', ['exception' => $exception]);
        }
    }

    private function applyWatermark(ProcessedFile $processedFile, WatermarkConfiguration $configuration): void
    {
        $localPath = $processedFile->getForLocalProcessing(false);
        $dimensions = getimagesize($localPath);
        if ($dimensions === false) {
            return;
        }

        $watermarked = $this->applier->apply($localPath, (int)$dimensions[0], (int)$dimensions[1], $configuration);
        // Overwrites the processed variant in place, keeping its identifier, so the watermarked file is
        // what gets served now and on every later cache hit. The processing cache stays valid without a
        // repository write, which also avoids the ProcessedFileRepository::update() signature difference
        // between TYPO3 13 and 14.
        $processedFile->updateWithLocalFile($watermarked);
    }
}
