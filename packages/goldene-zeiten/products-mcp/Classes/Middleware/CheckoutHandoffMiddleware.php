<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Middleware;

use GoldeneZeiten\Products\Mcp\Basket\McpBasketSessions;
use GoldeneZeiten\Products\Mcp\Handoff\CheckoutLinkSigner;
use GoldeneZeiten\Products\Mcp\Handoff\FrontendBasketMerger;
use GoldeneZeiten\Products\Mcp\Handoff\LandingPages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Opens a checkout link of an assistant: verifies its signature and expiry, puts the assistant's basket into
 * the visitor's frontend basket and redirects to the checkout page. A link that is invalid, expired or whose
 * basket is gone merges nothing and redirects to the basket page, where the visitor sees what they have.
 *
 * It runs after frontend authentication because the basket is merged into the frontend user session, which
 * also sets the session cookie of a visitor who had none.
 */
final readonly class CheckoutHandoffMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EndpointPaths $paths,
        private CheckoutLinkSigner $signer,
        private McpBasketSessions $sessions,
        private FrontendBasketMerger $merger,
        private LandingPages $landingPages,
        private Context $context
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site || !in_array($request->getMethod(), ['GET', 'HEAD'], true) || !$this->paths->isCheckout($site, $request)) {
            return $handler->handle($request);
        }
        $basketToken = $this->signer->verify(
            (string)($request->getQueryParams()['token'] ?? ''),
            (int)$this->context->getPropertyFromAspect('date', 'timestamp')
        );
        $basket = $basketToken === null ? null : $this->sessions->find($basketToken, $site);
        if ($basketToken === null || $basket === null) {
            return $this->redirect($this->landingPages->basket($site, $request));
        }
        $this->merger->merge($basketToken, $basket, $request);

        return $this->redirect($this->landingPages->checkout($site, $request));
    }

    /**
     * Never cached: the answer depends on the visitor's session and carries its cookie.
     */
    private function redirect(string $url): ResponseInterface
    {
        return new RedirectResponse($url, 303, ['Cache-Control' => 'no-store']);
    }
}
