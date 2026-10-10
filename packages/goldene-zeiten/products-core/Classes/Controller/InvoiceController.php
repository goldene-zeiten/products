<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoicePdfService;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceRenderer;
use GoldeneZeiten\Products\Core\Service\Invoice\InvoiceTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\ErrorController;

final class InvoiceController extends ActionController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceTokenService $invoiceTokenService,
        private readonly InvoiceRenderer $invoiceRenderer,
        private readonly InvoicePdfService $invoicePdfService,
        private readonly ErrorController $errorController
    ) {}

    /**
     * An unknown order and a wrong token look the same to the caller - a "not found" - so the link cannot be
     * used to probe which orders exist.
     */
    public function downloadAction(int $order, string $hash): ResponseInterface
    {
        $orderObject = $this->orderRepository->findByUidIgnoringStoragePage($order);
        if ($orderObject === null || !$this->invoiceTokenService->isValid($orderObject, $hash)) {
            throw new PropagateResponseException(
                $this->errorController->pageNotFoundAction($this->request, 'The invoice does not exist.'),
                1791666457
            );
        }

        $pdf = $this->invoicePdfService->renderToPdf($orderObject, $this->invoiceRenderer->render($orderObject));

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="invoice-%s.pdf"', $orderObject->getInvoiceNumber()))
            ->withBody($this->streamFactory->createStream($pdf));
    }
}
