<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\PricePeriod;
use GoldeneZeiten\Products\Core\Domain\Model\PriceTier;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Pricing\GraduatedPriceProvider;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

final class GraduatedPriceProviderTest extends AbstractFunctionalTestCase
{
    /**
     * @param array<int, string> $productTiers min quantity => price
     * @param array{price: string, mode: string, tiers?: array<int, string>}|null $article
     */
    #[Test]
    #[DataProvider('unitPriceDataProvider')]
    public function unitPriceFollowsThePricePrecedence(
        array $productTiers,
        ?string $productPeriodPrice,
        ?array $article,
        int $quantity,
        int $expectedCents
    ): void {
        $subject = $this->get(GraduatedPriceProvider::class);
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('100.00'));
        $product->setPriceTiers($this->tiers($productTiers));
        $product->setPricePeriods($this->periods($productPeriodPrice));
        $articleObject = null;
        if ($article !== null) {
            $articleObject = new Article();
            $articleObject->setPrice(Money::fromDecimalString($article['price']));
            $articleObject->setPriceMode($article['mode']);
            $articleObject->setPriceTiers($this->tiers($article['tiers'] ?? []));
        }

        $this->assertSame($expectedCents, $subject->getUnitPrice($product, $articleObject, $quantity)->getCents());
    }

    public static function unitPriceDataProvider(): \Generator
    {
        yield 'without tiers the product price applies' => [
            'productTiers' => [],
            'productPeriodPrice' => null,
            'article' => null,
            'quantity' => 1,
            'expectedCents' => 10000,
        ];
        yield 'the highest reached product tier applies' => [
            'productTiers' => [5 => '90.00', 10 => '80.00', 20 => '70.00'],
            'productPeriodPrice' => null,
            'article' => null,
            'quantity' => 12,
            'expectedCents' => 8000,
        ];
        yield 'a product tier applies to an article inheriting the product price' => [
            'productTiers' => [10 => '80.00'],
            'productPeriodPrice' => null,
            'article' => ['price' => '0.00', 'mode' => 'override'],
            'quantity' => 10,
            'expectedCents' => 8000,
        ];
        yield 'a product tier does not undercut an article with its own price' => [
            'productTiers' => [10 => '80.00'],
            'productPeriodPrice' => null,
            'article' => ['price' => '150.00', 'mode' => 'override'],
            'quantity' => 10,
            'expectedCents' => 15000,
        ];
        yield 'a surcharge article adds its surcharge to the product tier' => [
            'productTiers' => [10 => '80.00'],
            'productPeriodPrice' => null,
            'article' => ['price' => '5.00', 'mode' => 'surcharge'],
            'quantity' => 10,
            'expectedCents' => 8500,
        ];
        yield 'the article own tiers win over the product tiers' => [
            'productTiers' => [10 => '80.00'],
            'productPeriodPrice' => null,
            'article' => ['price' => '150.00', 'mode' => 'override', 'tiers' => [10 => '140.00']],
            'quantity' => 10,
            'expectedCents' => 14000,
        ];
        yield 'a tier without a price is ignored' => [
            'productTiers' => [10 => '0.00'],
            'productPeriodPrice' => null,
            'article' => null,
            'quantity' => 10,
            'expectedCents' => 10000,
        ];
        yield 'a product period does not undercut an article with its own price' => [
            'productTiers' => [],
            'productPeriodPrice' => '60.00',
            'article' => ['price' => '150.00', 'mode' => 'override'],
            'quantity' => 1,
            'expectedCents' => 15000,
        ];
        yield 'a surcharge article adds its surcharge to the product period price' => [
            'productTiers' => [],
            'productPeriodPrice' => '60.00',
            'article' => ['price' => '5.00', 'mode' => 'surcharge'],
            'quantity' => 1,
            'expectedCents' => 6500,
        ];
        yield 'the lower of tier and period price applies' => [
            'productTiers' => [1 => '70.00'],
            'productPeriodPrice' => '60.00',
            'article' => null,
            'quantity' => 1,
            'expectedCents' => 6000,
        ];
    }

    /**
     * @param array<int, string> $tiers
     * @return ObjectStorage<PriceTier>
     */
    private function tiers(array $tiers): ObjectStorage
    {
        /** @var ObjectStorage<PriceTier> $storage */
        $storage = new ObjectStorage();
        foreach ($tiers as $minQuantity => $price) {
            $tier = new PriceTier();
            $tier->setMinQuantity($minQuantity);
            $tier->setPrice(Money::fromDecimalString($price));
            $storage->attach($tier);
        }
        return $storage;
    }

    /**
     * @return ObjectStorage<PricePeriod>
     */
    private function periods(?string $price): ObjectStorage
    {
        /** @var ObjectStorage<PricePeriod> $storage */
        $storage = new ObjectStorage();
        if ($price !== null) {
            $period = new PricePeriod();
            $period->setPrice(Money::fromDecimalString($price));
            $period->setValidFrom(new \DateTime('-1 day'));
            $period->setValidUntil(new \DateTime('+1 day'));
            $storage->attach($period);
        }
        return $storage;
    }
}
