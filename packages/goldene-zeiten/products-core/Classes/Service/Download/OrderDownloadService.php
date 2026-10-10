<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Download;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * The files a customer bought with an order, and whether the order releases them yet.
 *
 * Files are only delivered through the download plugin, which streams them; keep them in a storage that is
 * not publicly reachable, or the order gate can be bypassed with the file's URL.
 */
final class OrderDownloadService
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    /**
     * Downloads are released once the payment is received - an invoice order therefore only after it has
     * been marked paid - and withdrawn again when the order is cancelled or the payment refunded.
     */
    public function isReleased(Order $order): bool
    {
        return $order->getPaymentStatus() === PaymentStatus::PAID && $order->getStatus() !== OrderStatus::CANCELLED;
    }

    /**
     * @return array<int, array{product: Product, files: ObjectStorage<FileReference>}>
     */
    public function getDownloads(Order $order): array
    {
        $result = [];
        $seenProductUids = [];
        foreach ($order->getItems() as $item) {
            $productUid = $item->getProduct();
            if ($productUid === 0 || isset($seenProductUids[$productUid])) {
                continue;
            }
            $seenProductUids[$productUid] = true;
            $product = $this->productRepository->findByUid($productUid);
            if ($product instanceof Product && $product->getDownloads()->count() > 0) {
                $result[] = ['product' => $product, 'files' => $product->getDownloads()];
            }
        }
        return $result;
    }

    /**
     * The download with the given file reference uid, if it belongs to a product of the order.
     */
    public function findDownload(Order $order, int $fileReferenceUid): ?FileReference
    {
        foreach ($this->getDownloads($order) as $download) {
            foreach ($download['files'] as $fileReference) {
                if ($fileReference->getUid() === $fileReferenceUid) {
                    return $fileReference;
                }
            }
        }
        return null;
    }
}
