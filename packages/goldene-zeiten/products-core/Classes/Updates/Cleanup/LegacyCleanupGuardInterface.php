<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates\Cleanup;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Vetoes the dropping of the legacy tt_products tables while some migration has not run yet.
 *
 * The final cleanup wizard drops every legacy tt_products table, including ones whose data (a dedicated
 * table, or a column on a shared table like tt_products) is migrated by an optional add-on rather than by
 * the core. Core must not drop such a table before that add-on has migrated it - but it also must not
 * depend on the add-on. So each migrating add-on registers a guard here, and the cleanup wizard refuses to
 * run while any guard objects. An add-on that is not installed registers no guard: nothing blocks cleanup,
 * and the separate migration-notice wizard is what warned that its data would not be migrated.
 */
#[AutoconfigureTag('products.legacy_cleanup_guard')]
interface LegacyCleanupGuardInterface
{
    /**
     * A human-readable reason why the legacy tables must not be dropped yet - typically "this add-on's
     * migration has not run, N rows still pending" - or null if this guard has no objection.
     */
    public function blockingReason(): ?string;
}
