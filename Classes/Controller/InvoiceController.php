<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Invoice\Exception\InvalidInvoiceTokenException;
use GoldeneZeiten\Products\Service\Invoice\InvoicePdfService;
use GoldeneZeiten\Products\Service\Invoice\InvoiceRenderer;
use GoldeneZeiten\Products\Service\Invoice\InvoiceTokenService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class InvoiceController extends ActionController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceTokenService $invoiceTokenService,
        private readonly InvoiceRenderer $invoiceRenderer,
        private readonly InvoicePdfService $invoicePdfService
    ) {}

    public function downloadAction(int $order, string $hash): ResponseInterface
    {
        $orderObject = $this->orderRepository->findByUidIgnoringStoragePage($order);
        if ($orderObject === null || !$this->invoiceTokenService->isValid($orderObject, $hash)) {
            throw new InvalidInvoiceTokenException(
                sprintf('Invalid or expired invoice download token for order %d.', $order),
                1752000001
            );
        }

        $pdf = $this->invoicePdfService->renderToPdf($orderObject, $this->invoiceRenderer->render($orderObject));

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="invoice-%s.pdf"', $orderObject->getInvoiceNumber()))
            ->withBody($this->streamFactory->createStream($pdf));
    }
}
