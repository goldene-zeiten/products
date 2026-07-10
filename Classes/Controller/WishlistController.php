<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class WishlistController extends ActionController
{
    public function __construct(
        private readonly WishlistService $wishlistService
    ) {}

    public function showAction(): ResponseInterface
    {
        $this->view->assignMultiple([
            'products' => $this->wishlistService->getItems($this->request),
            'wishlistCount' => $this->wishlistService->count($this->request),
        ]);
        return $this->htmlResponse();
    }

    public function addAction(int $product): ResponseInterface
    {
        $this->wishlistService->add($this->request, $product);
        return $this->redirect('show');
    }

    public function removeAction(int $product): ResponseInterface
    {
        $this->wishlistService->remove($this->request, $product);
        return $this->redirect('show');
    }

    public function moveUpAction(int $product): ResponseInterface
    {
        $this->wishlistService->moveUp($this->request, $product);
        return $this->redirect('show');
    }

    public function moveDownAction(int $product): ResponseInterface
    {
        $this->wishlistService->moveDown($this->request, $product);
        return $this->redirect('show');
    }
}
