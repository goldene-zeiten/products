<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Tests\Unit\Configuration;

use GoldeneZeiten\Products\Voucher\Configuration\GainedVoucherConfigurationFactory;
use GoldeneZeiten\Products\Voucher\Domain\Enum\VoucherDiscountType;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

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

        $this->assertTrue($configuration->isEnabled());
        $this->assertSame(5000, $configuration->getMinimumOrderValue()->getCents());
        $this->assertSame(VoucherDiscountType::PERCENTAGE, $configuration->getRewardType());
        $this->assertSame('10.00', $configuration->getRewardValue());
    }

    #[Test]
    public function settingsDefaultToDisabledWithoutASite(): void
    {
        $configuration = $this->subject()->create(new ServerRequest('http://localhost/'));

        $this->assertFalse($configuration->isEnabled());
        $this->assertSame(0, $configuration->getMinimumOrderValue()->getCents());
        $this->assertSame(VoucherDiscountType::FIXED, $configuration->getRewardType());
        $this->assertSame('5.00', $configuration->getRewardValue());
    }

    private function subject(): GainedVoucherConfigurationFactory
    {
        return new GainedVoucherConfigurationFactory();
    }
}
