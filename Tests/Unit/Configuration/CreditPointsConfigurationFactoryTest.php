<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Configuration;

use GoldeneZeiten\Products\Configuration\CreditPointsConfigurationFactory;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `products.creditPoints.*` are Site Settings - see ProductsConfigurationFactoryTest for the same
 * class of fix applied to ProductsConfigurationFactory. `earningTiers` (a `stringlist` Site
 * Setting) is deliberately not covered here: a bare `new Site(...)` bypasses TYPO3's typed
 * Settings/Sets resolution (falls back to `SiteSettings::createFromSettingsTree()`), which cannot
 * resolve an array-valued setting via its dotted path - only real Site Settings (backed by
 * `settings.definitions.yaml`) can. See CreditPointsConfigurationFactoryEarningTiersTest for that
 * coverage via a real Site.
 */
final class CreditPointsConfigurationFactoryTest extends UnitTestCase
{
    #[Test]
    public function settingsAreReadFromTheSite(): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => [
            'creditPoints' => [
                'enabled' => true,
                'moneyPerPoint' => '0.25',
                'earningMode' => 'basketTiered',
                'priceFactor' => 2.0,
            ],
        ]]]);
        $request = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);

        $configuration = $this->subject()->create($request);

        self::assertTrue($configuration->isEnabled());
        self::assertSame(25, $configuration->getMoneyPerPoint()->getCents());
        self::assertSame('basketTiered', $configuration->getEarningMode());
        self::assertSame(2.0, $configuration->getPriceFactor());
    }

    #[Test]
    public function settingsDefaultToDisabledWithoutASite(): void
    {
        $configuration = $this->subject()->create(new ServerRequest('http://localhost/'));

        self::assertFalse($configuration->isEnabled());
        self::assertSame(10, $configuration->getMoneyPerPoint()->getCents());
        self::assertSame('perProduct', $configuration->getEarningMode());
        self::assertSame([], $configuration->getEarningTiers());
        self::assertSame(0.0, $configuration->getPriceFactor());
    }

    private function subject(): CreditPointsConfigurationFactory
    {
        return new CreditPointsConfigurationFactory();
    }
}
