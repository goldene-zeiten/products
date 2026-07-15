<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\EventListener;

use GoldeneZeiten\Products\Wishlist\Service\WishlistService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * A merge failure must never break the login itself.
 */
#[AsEventListener]
final class MergeWishlistOnLoginListener
{
    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(AfterUserLoggedInEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->getUser() instanceof FrontendUserAuthentication || $request === null) {
            return;
        }
        try {
            $this->wishlistService->mergeSessionIntoAccount($request);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to merge session wishlist into account on login.', ['exception' => $exception]);
        }
    }
}
