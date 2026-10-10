<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Middleware;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The payment return clears the customer's basket and checkout session and leaves a message for the
 * checkout - all of which live in the frontend user session, so it has to run once the frontend user has
 * been authenticated. Running it before that silently skips every session write.
 */
final class PaymentMiddlewareOrderTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function thePaymentReturnRunsAfterFrontendAuthenticationAndBeforePageResolution(): void
    {
        // The resolved stack lists the innermost middleware first; reverse it into execution order.
        $executionOrder = array_reverse(array_keys($this->get('frontend.middlewares')->getArrayCopy()));
        $position = array_flip($executionOrder);

        $this->assertGreaterThan(
            $position['typo3/cms-frontend/authentication'],
            $position['goldene-zeiten/products/payment-return']
        );
        $this->assertLessThan(
            $position['typo3/cms-frontend/page-resolver'],
            $position['goldene-zeiten/products/payment-return']
        );
    }
}
