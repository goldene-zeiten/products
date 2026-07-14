<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Updates\Cleanup;

use GoldeneZeiten\Products\Core\Updates\Cleanup\LegacyCleanupGuardInterface;
use GoldeneZeiten\Products\Core\Updates\Cleanup\LegacyCleanupGuardRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LegacyCleanupGuardRegistryTest extends TestCase
{
    #[Test]
    public function noGuardsMeansNothingBlocksCleanup(): void
    {
        $this->assertSame([], (new LegacyCleanupGuardRegistry([]))->blockingReasons());
    }

    #[Test]
    public function collectsOnlyTheGuardsThatObject(): void
    {
        $registry = new LegacyCleanupGuardRegistry([
            $this->guard(null),
            $this->guard('addon A still migrating'),
            $this->guard(null),
            $this->guard('addon B still migrating'),
        ]);

        $this->assertSame(
            ['addon A still migrating', 'addon B still migrating'],
            $registry->blockingReasons(),
        );
    }

    private function guard(?string $reason): LegacyCleanupGuardInterface
    {
        return new class ($reason) implements LegacyCleanupGuardInterface {
            public function __construct(private readonly ?string $reason) {}

            public function blockingReason(): ?string
            {
                return $this->reason;
            }
        };
    }
}
