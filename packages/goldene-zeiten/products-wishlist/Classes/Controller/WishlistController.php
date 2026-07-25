<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\Controller;

use GoldeneZeiten\Products\Core\Security\FrontendRequestTokenValidator;
use GoldeneZeiten\Products\Wishlist\Service\WishlistService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class WishlistController extends ActionController
{
    private const REQUEST_TOKEN_SCOPE = 'products/wishlist';

    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly FrontendRequestTokenValidator $requestTokenValidator
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
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        $this->wishlistService->add($this->request, $product);
        return $this->redirect('show');
    }

    public function removeAction(int $product): ResponseInterface
    {
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        $this->wishlistService->remove($this->request, $product);
        return $this->redirect('show');
    }

    public function moveUpAction(int $product): ResponseInterface
    {
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        $this->wishlistService->moveUp($this->request, $product);
        return $this->redirect('show');
    }

    public function moveDownAction(int $product): ResponseInterface
    {
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        $this->wishlistService->moveDown($this->request, $product);
        return $this->redirect('show');
    }

    /**
     * A cross-site POST that cannot present the wishlist forms' request token is bounced back without
     * changing the visitor's wishlist.
     */
    private function rejectForgedRequest(): ?ResponseInterface
    {
        if ($this->requestTokenValidator->isValid(self::REQUEST_TOKEN_SCOPE)) {
            return null;
        }
        $this->addFlashMessage(
            (string)LocalizationUtility::translate('csrf.invalid_request', 'ProductsCore'),
            '',
            ContextualFeedbackSeverity::ERROR
        );

        return $this->redirect('show');
    }
}
