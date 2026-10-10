<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsOrderUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The retention cleanup selects orders per site, so a migrated order must carry the identifier of
 * the site its legacy record was stored in.
 */
final class TtProductsOrderUpgradeWizardSiteTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsOrderUpgradeWizardTest/sys_products_orders.csv');
    }

    #[Test]
    public function migratedOrderCarriesTheSiteOfItsLegacyPage(): void
    {
        $this->writeSiteConfiguration(
            'legacy-shop',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $siteIdentifiers = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order')
            ->select(['site_identifier'], 'tx_products_domain_model_order')
            ->fetchFirstColumn();
        $this->assertNotSame([], $siteIdentifiers);
        $this->assertSame(['legacy-shop'], array_values(array_unique($siteIdentifiers)));
    }
}
