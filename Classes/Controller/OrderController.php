<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Event\ModifyOrderTrackingEvent;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class OrderController extends ActionController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceTokenService $invoiceTokenService,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly OrderTokenService $orderTokenService,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function listAction(): ResponseInterface
    {
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        if ($frontendUserUid === 0) {
            return $this->htmlResponse();
        }

        $orders = $this->orderRepository->findByFrontendUser($frontendUserUid);
        $this->view->assign('orders', $orders);
        return $this->htmlResponse();
    }

    /**
     * Guest orders require an order-bound HMAC token; logged-in users can view their own orders by uid alone.
     */
    public function showAction(Order $order, ?string $hash = null): ResponseInterface
    {
        $frontendUserUid = $this->frontendUserResolver->getUid($this->request);
        $isOwner = $frontendUserUid !== 0 && $order->getFrontendUser() === $frontendUserUid;
        if (!$isOwner && !$this->orderTokenService->isValid($order, $hash)) {
            return $this->redirect('list');
        }

        $trackingEvent = new ModifyOrderTrackingEvent($order);
        $this->eventDispatcher->dispatch($trackingEvent);

        $this->view->assignMultiple([
            'order' => $order,
            'invoiceHash' => $this->invoiceTokenService->generateToken($order),
            'withdrawalHash' => $this->withdrawalTokenService->generateToken($order),
            'trackingLinks' => $trackingEvent->getTrackingLinks(),
        ]);
        return $this->htmlResponse();
    }
}
