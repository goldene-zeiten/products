<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Configuration;

use GoldeneZeiten\Products\Configuration\CreditPointsConfigurationFactory;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * `products.creditPoints.earningTiers` is a `stringlist` Site Setting - CreditPointsConfiguration
 * FactoryTest deliberately does not cover it with a bare `new Site(...)`, since that bypasses
 * TYPO3's typed Settings/Sets resolution (falls back to `SiteSettings::createFromSettingsTree()`,
 * which cannot resolve an array-valued setting via its dotted path). Only a real Site, backed by
 * an actual `config.yaml`/`settings.yaml` and looked up through `SiteFinder`, exercises the real
 * resolution path - same mechanism OrderCreationServiceCreditPointsTest already relies on for its
 * own Site Settings coverage.
 */
final class CreditPointsConfigurationFactoryEarningTiersTest extends AbstractFunctionalTestCase
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
        'goldene-zeiten/products',
    ];

    #[Test]
    public function earningTiersAreParsedFromTheStringlistSiteSetting(): void
    {
        $configuration = $this->get(CreditPointsConfigurationFactory::class)->create($this->requestWithEarningTiers([
            '50.00:10',
            '100.00:25',
        ]));

        $tiers = $configuration->getEarningTiers();
        $this->assertCount(2, $tiers);
        $this->assertSame(5000, $tiers[0]->getThreshold()->getCents());
        $this->assertSame(10, $tiers[0]->getPoints());
        $this->assertSame(10000, $tiers[1]->getThreshold()->getCents());
        $this->assertSame(25, $tiers[1]->getPoints());
    }

    #[Test]
    public function earningTiersDefaultToAnEmptyListWithoutTheSetting(): void
    {
        $configuration = $this->get(CreditPointsConfigurationFactory::class)->create($this->requestWithEarningTiers([]));

        $this->assertSame([], $configuration->getEarningTiers());
    }

    /**
     * @param string[] $earningTiers
     */
    private function requestWithEarningTiers(array $earningTiers): ServerRequestInterface
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products'],
                'settings' => [
                    'products' => [
                        'creditPoints' => ['earningTiers' => $earningTiers],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $site = $this->get(SiteFinder::class)->getSiteByIdentifier('products');

        return (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
    }
}
