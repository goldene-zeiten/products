<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Configuration;

use GoldeneZeiten\Products\Configuration\GainedVoucherConfigurationFactory;
use GoldeneZeiten\Products\Domain\Enum\VoucherDiscountType;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `products.vouchers.gained.*` are Site Settings - see ProductsConfigurationFactoryTest for the
 * same class of fix applied to ProductsConfigurationFactory.
 */
final class GainedVoucherConfigurationFactoryTest extends UnitTestCase
{
    #[Test]
    public function settingsAreReadFromTheSite(): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => [
            'vouchers' => ['gained' => [
                'enabled' => true,
                'minimumOrderValue' => '50.00',
                'rewardType' => 'percentage',
                'rewardValue' => '10.00',
            ]],
        ]]]);
        $request = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);

        $configuration = $this->subject()->create($request);

        self::assertTrue($configuration->isEnabled());
        self::assertSame(5000, $configuration->getMinimumOrderValue()->getCents());
        self::assertSame(VoucherDiscountType::PERCENTAGE, $configuration->getRewardType());
        self::assertSame('10.00', $configuration->getRewardValue());
    }

    #[Test]
    public function settingsDefaultToDisabledWithoutASite(): void
    {
        $configuration = $this->subject()->create(new ServerRequest('http://localhost/'));

        self::assertFalse($configuration->isEnabled());
        self::assertSame(0, $configuration->getMinimumOrderValue()->getCents());
        self::assertSame(VoucherDiscountType::FIXED, $configuration->getRewardType());
        self::assertSame('5.00', $configuration->getRewardValue());
    }

    private function subject(): GainedVoucherConfigurationFactory
    {
        return new GainedVoucherConfigurationFactory();
    }
}
