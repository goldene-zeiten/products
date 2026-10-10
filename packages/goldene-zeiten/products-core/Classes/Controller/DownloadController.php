<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Frontend\Controller\ErrorController;

final class DownloadController extends ActionController
{
    public function __construct(
        private readonly OrderTokenService $orderTokenService,
        private readonly ProductRepository $productRepository,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ErrorController $errorController
    ) {}

    public function listAction(Order $order, ?string $hash = null): ResponseInterface
    {
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $isOwner = $frontendUserUid !== 0 && $order->getFrontendUser() === $frontendUserUid;
        if (!$isOwner && !$this->orderTokenService->isValid($order, $hash)) {
            $this->sendToOrderHistory();
        }

        $this->view->assign('downloads', $this->resolveDownloads($order));
        return $this->htmlResponse();
    }

    /**
     * A caller who cannot prove the order is theirs is sent to the order history, where they can log in;
     * the response is propagated because this plugin is not cached and would otherwise only send a
     * redirect header on a fully rendered page.
     */
    private function sendToOrderHistory(): never
    {
        $orderHistoryPage = (int)($this->settings['pids']['orderHistoryPage'] ?? 0);
        if ($orderHistoryPage <= 0) {
            throw new PropagateResponseException(
                $this->errorController->accessDeniedAction($this->request, 'The order cannot be accessed.'),
                1791666467
            );
        }
        $uri = $this->uriBuilder->reset()->setTargetPageUid($orderHistoryPage)->setCreateAbsoluteUri(true)->build();
        throw new PropagateResponseException($this->redirectToUri($uri), 1791666475);
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
