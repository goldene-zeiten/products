<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates\Cleanup;

use GoldeneZeiten\Products\Core\Updates\Cleanup\LegacyCleanupGuardRegistry;
use GoldeneZeiten\Products\ExtensionPointFixture\DummyCleanupGuard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Proves the DI wiring an add-on relies on: a service tagged `products.legacy_cleanup_guard` (here the
 * fixture guard) is collected by the registry's TaggedIterator and its objection surfaced, so an add-on
 * can veto core dropping a legacy table until it has migrated. The recently-viewed add-on proves the same
 * seam with a real guard; this keeps the proof core-side and independent of any add-on.
 */
final class LegacyCleanupGuardTaggingTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-extension-point-fixture',
    ];

    #[Test]
    public function anExternallyTaggedGuardBlocksTheLegacyCleanup(): void
    {
        $reasons = $this->get(LegacyCleanupGuardRegistry::class)->blockingReasons();

        $this->assertContains(
            DummyCleanupGuard::REASON,
            $reasons,
            'A service tagged products.legacy_cleanup_guard must be collected by the registry and its reason surfaced.',
        );
    }
}
