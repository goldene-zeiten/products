<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\RateLimiter\RateLimiterFactory as SymfonyRateLimiterFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\RateLimiter\Storage\CachingFrameworkStorage;

/**
 * Throttles withdrawal confirmation attempts per client IP. The plain form resolves an order from
 * an order number + email pair, so without a bound the endpoint could be used to probe order
 * numbers at machine speed; the token path is included too, so a leaked link cannot be used to
 * brute-force the matching email.
 *
 * It drives Symfony's rate limiter over the caching-framework storage directly, rather than core's
 * {@see \TYPO3\CMS\Core\RateLimiter\RateLimiterFactory}, whose generic `create*()` helpers only
 * exist on TYPO3 14 - this stays working on the supported v13 too. The limit can be overridden per
 * installation via $GLOBALS['TYPO3_CONF_VARS']['SYS']['rateLimiter']['products-withdrawal-confirm']['limit'].
 */
#[Autoconfigure(public: true)]
final class WithdrawalRateLimiter
{
    private const LIMITER_ID = 'products-withdrawal-confirm';

    private const DEFAULT_LIMIT = 10;

    private const INTERVAL = '1 minute';

    public function __construct(
        private readonly CacheManager $cacheManager
    ) {}

    public function isWithinLimit(ServerRequestInterface $request): bool
    {
        $factory = new SymfonyRateLimiterFactory(
            [
                'id' => self::LIMITER_ID,
                'policy' => 'sliding_window',
                'limit' => $this->configuredLimit(),
                'interval' => self::INTERVAL,
            ],
            new CachingFrameworkStorage($this->cacheManager),
        );

        return $factory->create($this->clientIp($request))->consume(1)->isAccepted();
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            $normalizedParams = NormalizedParams::createFromRequest($request);
        }

        return $normalizedParams->getRemoteAddress();
    }

    private function configuredLimit(): int
    {
        return (int)($GLOBALS['TYPO3_CONF_VARS']['SYS']['rateLimiter'][self::LIMITER_ID]['limit'] ?? self::DEFAULT_LIMIT);
    }
}
