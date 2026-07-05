<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class FrontendUserResolver
{
    public function getUid(ServerRequestInterface $request): int
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication && ($frontendUser->user['uid'] ?? 0) > 0) {
            return (int)$frontendUser->user['uid'];
        }

        return 0;
    }
}
