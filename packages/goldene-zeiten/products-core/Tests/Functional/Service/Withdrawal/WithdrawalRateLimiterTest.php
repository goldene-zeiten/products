<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalRateLimiter;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * The withdrawal confirmation endpoint must not let a single client probe order numbers (plain
 * mode) or emails (token mode) at machine speed: after a bounded number of attempts in the window
 * the limiter turns further ones away, scoped per client IP.
 */
final class WithdrawalRateLimiterTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function allowsUpToTheLimitThenThrottlesFurtherAttempts(): void
    {
        $subject = $this->get(WithdrawalRateLimiter::class);
        $request = $this->requestFrom('203.0.113.17');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->assertTrue($subject->isWithinLimit($request), sprintf('Attempt %d should be within the limit.', $attempt));
        }

        $this->assertFalse($subject->isWithinLimit($request), 'The 11th attempt in the window is throttled.');
    }

    #[Test]
    public function theLimitIsScopedPerClientIp(): void
    {
        $subject = $this->get(WithdrawalRateLimiter::class);
        $exhausted = $this->requestFrom('203.0.113.17');
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $subject->isWithinLimit($exhausted);
        }

        $this->assertFalse($subject->isWithinLimit($exhausted), 'The exhausted IP is throttled.');
        $this->assertTrue($subject->isWithinLimit($this->requestFrom('198.51.100.14')), 'A different IP keeps its own budget.');
    }

    #[Test]
    public function theConfiguredLimitCanBeRaisedPerInstance(): void
    {
        $previous = $GLOBALS['TYPO3_CONF_VARS']['SYS']['rateLimiter'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['rateLimiter']['products-withdrawal-confirm']['limit'] = 100000;
        try {
            $subject = $this->get(WithdrawalRateLimiter::class);
            $request = $this->requestFrom('203.0.113.19');

            for ($attempt = 1; $attempt <= 11; $attempt++) {
                $this->assertTrue($subject->isWithinLimit($request), sprintf('Attempt %d is within the raised limit.', $attempt));
            }
        } finally {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['rateLimiter'] = $previous;
        }
    }

    private function requestFrom(string $ip): ServerRequestInterface
    {
        return new ServerRequest('http://localhost/', 'POST', 'php://temp', [], ['REMOTE_ADDR' => $ip]);
    }
}
