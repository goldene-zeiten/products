<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use GoldeneZeiten\Products\Backend\StorageFolderResolver;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_initialTaxClasses')]
final class InitialTaxClassesUpgradeWizard implements UpgradeWizardInterface, RepeatableInterface
{
    private const TABLE = 'tx_products_domain_model_taxclass';

    /**
     * @var array<string, string>
     */
    private const TAX_CLASSES = [
        'standard' => 'Standard rate',
        'reduced' => 'Reduced rate',
        'zero' => 'Zero rate',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly StorageFolderResolver $storageFolderResolver,
    ) {}

    public function getTitle(): string
    {
        return 'Seed the standard/reduced/zero product tax classes';
    }

    public function getDescription(): string
    {
        return 'Creates the standard, reduced and zero tax classes that new installations and the '
            . 'tt_products migration wizards rely on, unless they already exist.';
    }

    public function updateNecessary(): bool
    {
        return $this->missingCodes() !== [];
    }

    public function executeUpdate(): bool
    {
        $pid = $this->storageFolderResolver->resolve();
        foreach ($this->missingCodes() as $code) {
            $this->insertTaxClass($code, $pid);
        }
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    /**
     * @return string[]
     */
    private function missingCodes(): array
    {
        return array_values(array_diff(array_keys(self::TAX_CLASSES), $this->existingCodes()));
    }

    /**
     * @return string[]
     */
    private function existingCodes(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder->select('code')
            ->from(self::TABLE)
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()
            ->fetchFirstColumn();
        return array_map('strval', $rows);
    }

    private function insertTaxClass(string $code, int $pid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->insert(self::TABLE)->values([
            'pid' => $pid,
            'code' => $code,
            'title' => self::TAX_CLASSES[$code],
        ])->executeStatement();
    }
}
