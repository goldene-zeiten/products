<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Invoice\InvoiceTokenService;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class OrderController extends ActionController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceTokenService $invoiceTokenService,
        private readonly WithdrawalTokenService $withdrawalTokenService
    ) {}

    public function listAction(): ResponseInterface
    {
        $frontendUser = $this->request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication || ($frontendUser->user['uid'] ?? 0) === 0) {
            return $this->htmlResponse();
        }

        $orders = $this->orderRepository->findByFrontendUser((int)$frontendUser->user['uid']);
        $this->view->assign('orders', $orders);
        return $this->htmlResponse();
    }

    public function showAction(Order $order): ResponseInterface
    {
        $frontendUser = $this->request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication || $order->getFrontendUser() !== (int)($frontendUser->user['uid'] ?? 0)) {
            return $this->redirect('list');
        }

        $this->view->assignMultiple([
            'order' => $order,
            'invoiceHash' => $this->invoiceTokenService->generateToken($order),
            'withdrawalHash' => $this->withdrawalTokenService->generateToken($order),
        ]);
        return $this->htmlResponse();
    }
}
