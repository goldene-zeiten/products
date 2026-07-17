<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Solr\Tests\Functional\Indexing;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Drives EXT:solr's real Index Queue initialization for the product indexing configuration shipped by
 * this add-on and asserts the resulting {@see tx_solr_indexqueue_item} rows.
 *
 * Queue initialization is a database-only operation: it reads the site's index-queue TypoScript, selects
 * the matching records from the site rootline and writes one queue row per record. No Solr server is
 * contacted, so a *configured but unreachable* connection on the site is enough for the EXT:solr Site to
 * be built.
 */
final class ProductIndexQueueInitializationTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $coreExtensionsToLoad = [
        'reports',
        'scheduler',
        'tstemplate',
        'fluid_styled_content',
    ];

    protected array $testExtensionsToLoad = [
        'apache-solr-for-typo3/solr',
        'goldene-zeiten/products-solr',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/productIndexQueue.csv');
        // The site depends on the real shipped set, exactly as a production site would: EXT:solr builds the
        // root page's TypoScript from the site's sets, so this is what activates the add-on's index-queue
        // configuration (plugin.tx_solr.index.queue.products). The set pulls EXT:solr's base TypoScript on
        // both cores - via EXT:solr's own set on 14 (this set's optional dependency), via the gated static
        // import on 13 - so the "products" indexing configuration the queue initialization reads is present.
        $this->writeSiteConfiguration(
            'products-solr-test',
            $this->buildSiteConfiguration(1, 'http://localhost/', 'Products Solr', [
                'dependencies' => [
                    'goldene-zeiten/products-solr',
                ],
            ]),
            [
                array_merge(
                    $this->buildDefaultLanguageConfiguration('en', '/'),
                    [
                        // A configured - not reachable - connection is all Site construction needs; the
                        // queue initialization never opens it.
                        'solr_enabled_read' => 1,
                        'solr_scheme_read' => 'http',
                        'solr_host_read' => 'localhost',
                        'solr_port_read' => 8983,
                        'solr_path_read' => '/',
                        'solr_core_read' => 'core_en',
                    ],
                ),
            ],
        );
    }

    // EXT:solr 14.0.0-beta3 still calls the v14-deprecated BackendUtility::isTableLocalizable() from its
    // own IndexQueue\Initializer\AbstractInitializer while building the queue. That deprecation originates
    // in the third-party extension - not in this add-on or this test - and cannot be fixed here; it is
    // ignored so the real initialization can still be exercised. On TYPO3 13 the method is not deprecated
    // and nothing is ignored.
    #[IgnoreDeprecations]
    #[Test]
    public function initializationEnqueuesEveryProductInTheSiteRootline(): void
    {
        // EXT:solr's Index Queue initializer targets MySQL/MariaDB and does not populate the queue on
        // PostgreSQL (it reports success but inserts no rows). The products indexing configuration is
        // covered on the MySQL-family functional lanes and end to end in the Solr acceptance combination.
        if ($this->get(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item')
            ->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->markTestSkipped('EXT:solr Index Queue initialization is not supported on PostgreSQL.');
        }

        $site = $this->get(SiteRepository::class)->getSiteByPageId(1);
        $this->assertInstanceOf(Site::class, $site);

        // Proves EXT:solr's base TypoScript is loaded through the set on both cores: the "pages" index-queue
        // configuration exists only in EXT:solr's base setup, while "products" is this add-on's. Their
        // co-presence confirms the optional-dependency wiring (core 14) and the gated static import
        // (core 13) both work, and that neither path double-loads the base into an invalid state.
        $enabledConfigurations = $site->getSolrConfiguration()->getEnabledIndexQueueConfigurationNames();
        $this->assertContains('pages', $enabledConfigurations);
        $this->assertContains('products', $enabledConfigurations);

        // EXT:solr does not register the initialization service as a public service; its own backend
        // Index Queue module obtains it the same way (see IndexQueueModuleController). Only the "products"
        // configuration is initialized, so the enabled "pages" queue adds no rows.
        $result = GeneralUtility::makeInstance(QueueInitializationService::class)
            ->initializeBySiteAndIndexConfiguration($site, 'products');

        $this->assertSame(['products' => true], $result);
        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/Results/indexQueueProducts.csv');
    }
}
