<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Service\Basket\BasketService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class BasketController extends ActionController
{
    public function __construct(
        private readonly BasketService $basketService
    ) {}

    public function showAction(): ResponseInterface
    {
        $basketViewModel = $this->basketService->getBasketViewModel($this->request);
        $this->view->assign('basket', $basketViewModel);
        return $this->htmlResponse();
    }

    public function addAction(int $product, ?int $article = null, int $quantity = 1): ResponseInterface
    {
        $this->basketService->add($this->request, $product, $article, $quantity);
        return $this->redirect('show');
    }

    public function updateAction(int $product, ?int $article = null, int $quantity = 1): ResponseInterface
    {
        $this->basketService->update($this->request, $product, $article, $quantity);
        return $this->redirect('show');
    }

    public function removeAction(int $product, ?int $article = null): ResponseInterface
    {
        $this->basketService->remove($this->request, $product, $article);
        return $this->redirect('show');
    }
}
