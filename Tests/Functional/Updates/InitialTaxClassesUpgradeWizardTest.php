<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\InitialTaxClassesUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;

final class InitialTaxClassesUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private const TABLE = 'tx_products_domain_model_taxclass';

    #[Test]
    public function updateIsNecessaryWhenNoTaxClassesExistYet(): void
    {
        $subject = $this->get(InitialTaxClassesUpgradeWizard::class);

        $this->assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateSeedsAllThreeTaxClasses(): void
    {
        $subject = $this->get(InitialTaxClassesUpgradeWizard::class);

        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/tax_classes_seeded.csv');
        $this->assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $subject = $this->get(InitialTaxClassesUpgradeWizard::class);

        $this->assertTrue($subject->executeUpdate());
        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/tax_classes_seeded.csv');
    }

    #[Test]
    public function updateIsNotNecessaryWhenACodeAlreadyExists(): void
    {
        $subject = $this->get(InitialTaxClassesUpgradeWizard::class);
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->insert(self::TABLE)->values(['code' => 'standard', 'title' => 'Custom standard'])->executeStatement();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/tax_classes_seeded_existing_standard.csv');
    }
}
