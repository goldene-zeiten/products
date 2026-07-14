<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\RecentlyViewedMigrationNoticeUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The recently-viewed add-on is deliberately not loaded here, which is the situation this notice exists
 * for: a core-only upgrade. The "steps aside once the add-on is installed" case is covered by the add-on's
 * own suite, where the add-on is naturally active - keeping this core suite add-on-free.
 */
final class RecentlyViewedMigrationNoticeUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    private function subject(): RecentlyViewedMigrationNoticeUpgradeWizard
    {
        $subject = $this->get(RecentlyViewedMigrationNoticeUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());
        return $subject;
    }

    #[Test]
    public function isNotNecessaryWithoutLegacyData(): void
    {
        $this->assertFalse($this->subject()->updateNecessary());
    }

    #[Test]
    public function advisesInstallingTheAddonWhileLegacyDataIsPresentAndItIsMissing(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RecentlyViewedMigrationNoticeUpgradeWizardTest/legacy_visited_products.csv');
        $subject = $this->subject();

        $this->assertTrue($subject->updateNecessary());
        $this->assertStringContainsString('products-recently-viewed', $subject->getDescription());

        // Advisory only: executing it changes nothing, so it stays necessary - it is repeatable and only
        // steps aside once the add-on is installed.
        $this->assertTrue($subject->executeUpdate());
        $this->assertTrue($subject->updateNecessary());
    }
}
