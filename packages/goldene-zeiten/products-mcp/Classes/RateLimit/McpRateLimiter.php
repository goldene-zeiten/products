<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\RateLimit;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory as SymfonyRateLimiterFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\RateLimiter\Storage\CachingFrameworkStorage;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Counts the requests of one client IP against the per-minute limit a site sets for a {@see RateLimitScope}.
 * Call {@see self::consume()} once per request; the counters live in TYPO3's caching framework, so they are
 * shared by all PHP processes.
 */
final readonly class McpRateLimiter
{
    private const INTERVAL = '1 minute';

    public function __construct(
        private CacheManager $cacheManager
    ) {}

    /**
     * @return int|null Seconds until the client may retry when the limit is exceeded, null when the request
     *                  is within the limit or the scope is not limited
     */
    public function consume(Site $site, ServerRequestInterface $request, RateLimitScope $scope): ?int
    {
        $limit = (int)$site->getSettings()->get($scope->settingKey(), $scope->defaultLimit());
        if ($limit <= 0) {
            return null;
        }
        $factory = new SymfonyRateLimiterFactory(
            ['id' => $scope->value, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => self::INTERVAL],
            new CachingFrameworkStorage($this->cacheManager)
        );
        $result = $factory->create($site->getIdentifier() . '|' . $this->clientIp($request))->consume(1);
        if ($result->isAccepted()) {
            return null;
        }

        return max(1, $result->getRetryAfter()->getTimestamp() - time());
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            $normalizedParams = NormalizedParams::createFromRequest($request);
        }

        return $normalizedParams->getRemoteAddress();
    }
}
