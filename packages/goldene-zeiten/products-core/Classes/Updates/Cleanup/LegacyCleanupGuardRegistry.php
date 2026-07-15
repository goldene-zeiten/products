<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates\Cleanup;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Collects the reasons every registered {@see LegacyCleanupGuardInterface} gives for not dropping the
 * legacy tables yet. With no guard registered nothing blocks cleanup, which is the correct state for a
 * shop that installed no migrating add-ons.
 */
final class LegacyCleanupGuardRegistry
{
    /**
     * @var LegacyCleanupGuardInterface[]
     */
    private array $guards;

    /**
     * @param iterable<LegacyCleanupGuardInterface> $guards
     */
    public function __construct(
        #[TaggedIterator('products.legacy_cleanup_guard')]
        iterable $guards
    ) {
        $this->guards = [...$guards];
    }

    /**
     * @return string[] one reason per guard that objects, empty when cleanup may proceed
     */
    public function blockingReasons(): array
    {
        $reasons = [];
        foreach ($this->guards as $guard) {
            $reason = $guard->blockingReason();
            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }
}
