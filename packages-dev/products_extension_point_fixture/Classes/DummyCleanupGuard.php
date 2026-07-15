<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ExtensionPointFixture;

use GoldeneZeiten\Products\Core\Updates\Cleanup\LegacyCleanupGuardInterface;

/**
 * A dummy legacy-cleanup guard proving an externally registered guard is tag-collected by
 * {@see \GoldeneZeiten\Products\Core\Updates\Cleanup\LegacyCleanupGuardRegistry} and its objection
 * surfaced, so an add-on can veto core's legacy-table drop until it has migrated.
 */
final class DummyCleanupGuard implements LegacyCleanupGuardInterface
{
    public const REASON = 'Fixture guard still needs the legacy table.';

    public function blockingReason(): string
    {
        return self::REASON;
    }
}
