<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\OrderSettingsResolver;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Settings\SettingsInterface;

final class TermsSnapshotService
{
    private const RELATION_TABLE = 'sys_file_reference';
    private const FOREIGN_TABLE = 'tx_products_domain_model_order';
    private const FOREIGN_FIELD = 'terms_document';

    public function __construct(
        private readonly OrderSettingsResolver $settingsResolver,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Requires $order to already be persisted (uid + pid set) - the sys_file_reference row
     * needs a uid_foreign to point at. Writes the relation directly via ConnectionPool rather
     * than through Extbase's ObjectStorage<FileReference>: Extbase\Domain\Model\FileReference
     * ::setOriginalResource() only accepts an *existing* Core\Resource\FileReference (i.e. a
     * row that already exists in sys_file_reference), not a plain File as returned by
     * ResourceStorage::copyFile() - there is no supported Extbase-level API to create a brand
     * new file relation from scratch, so this mirrors {@see PriceHistoryRecorder}'s approach of
     * writing this kind of low-level relation via ConnectionPool directly.
     */
    public function snapshot(Order $order): void
    {
        $identifier = $this->getSetting($this->settingsResolver->getSettings($order), 'products.email.agbAttachment', '');
        if ($identifier === '' || $order->getUid() === null) {
            return;
        }

        try {
            $file = $this->resourceFactory->getFileObjectFromCombinedIdentifier($identifier);
            if ($file === null) {
                return;
            }

            $storage = $file->getStorage();
            $archiveFolder = $storage->hasFolder('order-terms-archive')
                ? $storage->getFolder('order-terms-archive')
                : $storage->createFolder('order-terms-archive');

            $targetFileName = $this->sanitizeFilename($order->getOrderNumber() . '-agb.pdf');
            $copiedFile = $storage->copyFile($file, $archiveFolder, $targetFileName);

            $this->insertFileReference($order, $copiedFile->getUid());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to snapshot AGB file for order %s.', $order->getOrderNumber()),
                ['exception' => $exception]
            );
        }
    }

    private function insertFileReference(Order $order, int $fileUid): void
    {
        $now = time();
        $this->connectionPool->getConnectionForTable(self::RELATION_TABLE)->insert(self::RELATION_TABLE, [
            'pid' => $order->getPid(),
            'tstamp' => $now,
            'crdate' => $now,
            'table_local' => 'sys_file',
            'uid_local' => $fileUid,
            'tablenames' => self::FOREIGN_TABLE,
            'uid_foreign' => $order->getUid(),
            'fieldname' => self::FOREIGN_FIELD,
            'sorting_foreign' => 0,
        ]);
    }

    private function getSetting(SettingsInterface $settings, string $path, string $default): string
    {
        return $settings->has($path) ? (string)$settings->get($path) : $default;
    }

    private function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename) ?: 'agb.pdf';
    }
}
