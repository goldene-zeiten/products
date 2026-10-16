<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\BasketSummary\Middleware;

use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Answers `/products/basket-summary` with the item count and the total of the visitor's basket, for the
 * basket badge in the page header. The page itself stays cacheable; the badge asks this endpoint.
 */
final class BasketSummaryMiddleware implements MiddlewareInterface
{
    public const PATH = '/products/basket-summary';

    public function __construct(private readonly BasketService $basketService) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== self::PATH || !$request->getAttribute('site') instanceof Site) {
            return $handler->handle($request);
        }
        $basket = $this->basketService->getBasketViewModel($request);
        return (new JsonResponse([
            'count' => $basket->getItemCount(),
            'total' => $basket->getTotalGross()->getDecimalString(),
            'currency' => $basket->getCurrency(),
        ]))->withHeader('Cache-Control', 'no-store, private');
    }
}
