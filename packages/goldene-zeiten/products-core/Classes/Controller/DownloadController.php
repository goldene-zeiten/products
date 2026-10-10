<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Download\OrderDownloadService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\ErrorController;

final class DownloadController extends ActionController
{
    public function __construct(
        private readonly OrderTokenService $orderTokenService,
        private readonly OrderDownloadService $orderDownloadService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ErrorController $errorController
    ) {}

    public function listAction(Order $order, ?string $hash = null): ResponseInterface
    {
        $this->assertCallerMayAccess($order, $hash);

        $this->view->assignMultiple([
            'order' => $order,
            'hash' => $hash,
            'released' => $this->orderDownloadService->isReleased($order),
            'downloads' => $this->orderDownloadService->getDownloads($order),
        ]);
        return $this->htmlResponse();
    }

    /**
     * Streams a bought file. The response is propagated as the response of the whole request, as this
     * plugin is not cached and its own response would only be embedded into the rendered page.
     */
    public function fileAction(Order $order, int $file, ?string $hash = null): ResponseInterface
    {
        $this->assertCallerMayAccess($order, $hash);
        $fileReference = $this->orderDownloadService->isReleased($order)
            ? $this->orderDownloadService->findDownload($order, $file)
            : null;
        $originalFile = $fileReference?->getOriginalResource()->getOriginalFile();
        if (!$originalFile instanceof FileInterface) {
            throw new PropagateResponseException(
                $this->errorController->pageNotFoundAction($this->request, 'The download does not exist.'),
                1791666727
            );
        }

        throw new PropagateResponseException(
            $originalFile->getStorage()->streamFile($originalFile, true),
            1791666740
        );
    }

    private function assertCallerMayAccess(Order $order, ?string $hash): void
    {
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $isOwner = $frontendUserUid !== 0 && $order->getFrontendUser() === $frontendUserUid;
        if (!$isOwner && !$this->orderTokenService->isValid($order, $hash)) {
            $this->sendToOrderHistory();
        }
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
}
