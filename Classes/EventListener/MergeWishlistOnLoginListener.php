<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Ports legacy tx_ttproducts_control_memo::copySession2Feuser() (wired onto felogin's
 * login_confirmed hook): a guest's session wishlist is merged into the account the moment they
 * log in, not silently left behind. Fires on every FE login (backend logins carry a
 * BackendUserAuthentication and are ignored); a failure here must never break the login itself,
 * same reasoning as SendOrderEmailsListener never failing the request over a mail error.
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
