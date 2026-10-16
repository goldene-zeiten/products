<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\BasketSummary\Controller;

use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Renders the basket badge with the count and total already filled in, for `lib.productsBasketSummaryUncached`.
 */
final class BasketSummaryController extends ActionController
{
    public function __construct(private readonly BasketService $basketService) {}

    public function summaryAction(): ResponseInterface
    {
        $basket = $this->basketService->getBasketViewModel($this->request);
        $this->view->assignMultiple([
            'count' => $basket->getItemCount(),
            'totalGross' => $basket->getTotalGross(),
            'currency' => $basket->getCurrency(),
            'basketPage' => (int)($this->settings['pids']['basketPage'] ?? 0),
        ]);
        return $this->htmlResponse();
    }
}
