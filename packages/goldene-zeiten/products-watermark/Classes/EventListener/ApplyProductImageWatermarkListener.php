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
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
        private readonly ConnectionPool $connectionPool,
        private readonly FrontendInterface $runtimeCache,
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
        if ($processedFile->usesOriginalFile()) {
            $this->storeAsOwnVariant($processedFile, $watermarked, (int)$dimensions[0], (int)$dimensions[1]);
            return;
        }
        // Overwrites the processed variant in place, keeping its identifier, so the watermarked file is
        // what gets served now and on every later cache hit; its processed-file record stays valid as is.
        $processedFile->updateWithLocalFile($watermarked);
    }

    /**
     * When the requested size needs no processing, core points the processed file at the original and has
     * already persisted it that way. The watermarked image is stored under a name of its own instead, and
     * the record is updated, so later requests find and serve the watermarked variant. Only the columns
     * that locate the file change; the record's checksum and configuration stay as core persisted them.
     */
    private function storeAsOwnVariant(ProcessedFile $processedFile, string $watermarked, int $width, int $height): void
    {
        $processedFile->setName($this->variantName($processedFile));
        $processedFile->updateWithLocalFile($watermarked);
        $processedFile->updateProperties([
            'width' => $width,
            'height' => $height,
        ]);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_processedfile');
        $queryBuilder
            ->update('sys_file_processedfile')
            ->set('identifier', $processedFile->getIdentifier())
            ->set('name', $processedFile->getName())
            ->set('width', $width)
            ->set('height', $height)
            ->where($queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter((int)$processedFile->getUid(), Connection::PARAM_INT)
            ))
            ->executeStatement();
        // TYPO3 14 keeps the processed-file rows of the current request in the runtime cache; drop them so
        // a second use of the image in this request finds the updated record too.
        $this->runtimeCache->flushByTag('processed-files');
    }

    private function variantName(ProcessedFile $processedFile): string
    {
        $originalFile = $processedFile->getOriginalFile();
        $hash = sha1(sprintf(
            '%d|%s|%s',
            $originalFile->getUid(),
            $processedFile->getTaskIdentifier(),
            json_encode($processedFile->getProcessingConfiguration())
        ));

        return sprintf('wm_%s_%s.%s', $originalFile->getNameWithoutExtension(), substr($hash, 0, 10), $processedFile->getExtension());
    }
}
