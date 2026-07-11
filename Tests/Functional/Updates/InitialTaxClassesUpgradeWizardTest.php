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

    private InitialTaxClassesUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(InitialTaxClassesUpgradeWizard::class);
    }

    #[Test]
    public function updateIsNecessaryWhenNoTaxClassesExistYet(): void
    {
        $this->assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateSeedsAllThreeTaxClasses(): void
    {
        $this->assertTrue($this->subject->executeUpdate());
        $this->assertSame(['reduced', 'standard', 'zero'], $this->fetchCodes());
        $this->assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $this->assertTrue($this->subject->executeUpdate());
        $this->assertTrue($this->subject->executeUpdate());
        $this->assertSame(['reduced', 'standard', 'zero'], $this->fetchCodes());
    }

    #[Test]
    public function updateIsNotNecessaryWhenACodeAlreadyExists(): void
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->insert(self::TABLE)->values(['code' => 'standard', 'title' => 'Custom standard'])->executeStatement();

        $this->assertTrue($this->subject->updateNecessary());
        $this->assertTrue($this->subject->executeUpdate());
        $this->assertSame(['reduced', 'standard', 'zero'], $this->fetchCodes());
    }

    /**
     * @return string[]
     */
    private function fetchCodes(): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder->select('code')->from(self::TABLE)->orderBy('code')->executeQuery()->fetchFirstColumn();
        $codes = array_map('strval', $rows);
        sort($codes);
        return $codes;
    }
}
