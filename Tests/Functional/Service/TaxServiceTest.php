<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service;

use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Repository\TaxClassRepository;
use GoldeneZeiten\Products\Domain\Repository\TaxRateRepository;
use GoldeneZeiten\Products\Service\TaxService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class TaxServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private TaxClass $taxClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tax_rates.csv');
        $taxClass = $this->get(TaxClassRepository::class)->findByUid(1);
        self::assertInstanceOf(TaxClass::class, $taxClass);
        $this->taxClass = $taxClass;
    }

    #[Test]
    public function getTaxRateReturnsZeroForANullTaxClass(): void
    {
        self::assertSame(0.0, $this->subject()->getTaxRate(null));
    }

    #[Test]
    public function getTaxRateReturnsAFractionNotTheStoredWholePercentage(): void
    {
        // The fixture stores rate=19.00 (a 19% whole percentage, as edited in the backend) -
        // getTaxRate() must convert it to 0.19 for direct "1 + rate" multiplication.
        self::assertSame(0.19, $this->subject()->getTaxRate($this->taxClass, 'DE'));
    }

    #[Test]
    public function getTaxRateFallsBackToTheDefaultCountryWhenTheRequestedCountryHasNoRate(): void
    {
        self::assertSame(0.19, $this->subject('DE')->getTaxRate($this->taxClass, 'FR'));
    }

    #[Test]
    public function getTaxRateReturnsZeroWhenNeitherTheRequestedNorDefaultCountryHasARate(): void
    {
        self::assertSame(0.0, $this->subject('AT')->getTaxRate($this->taxClass, 'FR'));
    }

    private function subject(string $defaultCountry = 'DE'): TaxService
    {
        return new TaxService($this->get(TaxRateRepository::class), $this->fakeConfigurationManager($defaultCountry));
    }

    private function fakeConfigurationManager(string $defaultCountry): ConfigurationManagerInterface
    {
        return new class ($defaultCountry) implements ConfigurationManagerInterface {
            public function __construct(private readonly string $defaultCountry) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['tax' => ['defaultCountry' => $this->defaultCountry]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }
}
