<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A manufacturer is shown translated in a translated site language, with the language-independent fields of
 * its default language.
 */
final class ManufacturerTranslationTest extends AbstractFrontendTestCase
{
    protected const LANGUAGE_PRESETS = [
        'de' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
        ],
        'en' => [
            'id' => 1,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'pids' => [
                            'storageFolder' => 2,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('de', '/'), $this->buildLanguageConfiguration('en', '/en/', ['de'])]
        );
    }

    #[Test]
    public function theListShowsTheBrandNameOfTheTranslation(): void
    {
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ManufacturerListTest/manufacturers.csv');
        $this->translateManufacturer(1);
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update('tt_content', ['hidden' => 1], ['pid' => 2]);
        $connection->update('tt_content', ['hidden' => 0], ['uid' => 812]);

        $german = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();
        $english = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/en/shop'))->getBody();

        $this->assertStringContainsString('>Alpha Rings<', $german);
        $this->assertStringContainsString('>Alpha Rings International<', $english);
        $this->assertStringNotContainsString('>Alpha Rings<', $english);
        $this->assertStringContainsString('>Beta Rings<', $english);
    }

    #[Test]
    public function theDetailPageShowsTheTranslationAndKeepsTheLanguageIndependentFields(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductManufacturerTest/products_with_manufacturer.csv');
        $this->translateManufacturer(1);
        $this->translateProduct(1);

        $parameters = ['tx_productscore_productdetail[product]' => '1'];
        $parameters['cHash'] = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=1');
        $body = (string)$this->executeFrontendSubRequest((new InternalRequest('http://localhost/en/shop'))->withQueryParameters($parameters))->getBody();

        $this->assertStringContainsString('Alpha Rings International', $body);
        $this->assertStringContainsString('Hauptstrasse 1', $body);
        $this->assertStringContainsString('info@stelmach.example.com', $body);
    }

    private function translateManufacturer(int $uid): void
    {
        $this->getConnectionPool()->getConnectionForTable('pages')->insert('pages', [
            'pid' => 1,
            'sys_language_uid' => 1,
            'l10n_parent' => 2,
            'title' => 'Shop',
            'slug' => '/shop',
        ]);
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_manufacturer');
        $original = $connection->select(['*'], 'tx_products_domain_model_manufacturer', ['uid' => $uid])->fetchAssociative();
        unset($original['uid']);
        $connection->insert('tx_products_domain_model_manufacturer', array_merge($original, [
            'sys_language_uid' => 1,
            'l10n_parent' => $uid,
            'name' => 'Alpha Rings Ltd',
            'brand_name' => 'Alpha Rings International',
        ]));
    }

    private function translateProduct(int $uid): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_product');
        $original = $connection->select(['*'], 'tx_products_domain_model_product', ['uid' => $uid])->fetchAssociative();
        unset($original['uid']);
        $connection->insert('tx_products_domain_model_product', array_merge($original, [
            'sys_language_uid' => 1,
            'l10n_parent' => $uid,
            'title' => 'Engagement ring (en)',
        ]));
    }
}
