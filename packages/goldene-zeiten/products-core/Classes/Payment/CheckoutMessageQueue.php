<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Leaves a message for the checkout plugin from outside of it - the payment return middleware, which runs
 * before Extbase and before core's flash message queue can reach the frontend user. The checkout renders it
 * with `<f:flashMessages />` on the next page it shows.
 */
final class CheckoutMessageQueue
{
    private const IDENTIFIER = 'extbase.flashmessages.tx_productscore_checkout';

    public function addError(FrontendUserAuthentication $frontendUser, string $message): void
    {
        $queued = $frontendUser->getSessionData(self::IDENTIFIER);
        $queued = is_array($queued) ? $queued : [];
        $queued[] = json_encode(new FlashMessage($message, '', ContextualFeedbackSeverity::ERROR, true));
        $frontendUser->setAndSaveSessionData(self::IDENTIFIER, $queued);
    }
}
