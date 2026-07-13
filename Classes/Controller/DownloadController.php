<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\OrderTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

final class DownloadController extends ActionController
{
    public function __construct(
        private readonly OrderTokenService $orderTokenService,
        private readonly ProductRepository $productRepository,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function listAction(Order $order, ?string $hash = null): ResponseInterface
    {
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $isOwner = $frontendUserUid !== 0 && $order->getFrontendUser() === $frontendUserUid;
        if (!$isOwner && !$this->orderTokenService->isValid($order, $hash)) {
            return $this->redirect('list', 'Order');
        }

        $this->view->assign('downloads', $this->resolveDownloads($order));
        return $this->htmlResponse();
    }

    /**
     * @return array<int, array{product: Product, files: ObjectStorage<FileReference>}>
     */
    private function resolveDownloads(Order $order): array
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
}
