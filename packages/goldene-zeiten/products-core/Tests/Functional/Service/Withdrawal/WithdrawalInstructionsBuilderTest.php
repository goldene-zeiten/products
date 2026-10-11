<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\TraderIdentity;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalInstructionVariant;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalInstructionsBuilder;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The instruction texts are the official model wording of Annex I(A) and I(B) of Directive 2011/83/EU,
 * with the trader data filled in.
 */
final class WithdrawalInstructionsBuilderTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function theEnglishGoodsInstructionsUseTheOfficialWording(): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);

        $instructions = $subject->build($this->settings(), WithdrawalInstructionVariant::GOODS, 'https://shop.example/withdraw', 'en');

        $this->assertSame('You have the right to withdraw from this contract within 14 days without giving any reason.', $instructions->right);
        $this->assertSame(
            'The withdrawal period will expire after 14 days from the day on which you acquire, or a third party other than the carrier and indicated by you acquires, physical possession of the goods.',
            $instructions->period
        );
        $this->assertSame(
            'To exercise the right of withdrawal, you must inform us (Shop GmbH, Main Street 1, 12345 Sampletown, telephone: +49 30 1234, e-mail: widerruf@shop.example) of your decision to withdraw from this contract by an unequivocal statement (e.g. a letter sent by post, fax or e-mail). You may use the attached model withdrawal form, but it is not obligatory.',
            $instructions->exercise
        );
        $this->assertSame(
            'We may withhold reimbursement until we have received the goods back or you have supplied evidence of having sent back the goods, whichever is the earliest.',
            $instructions->withholding
        );
        $this->assertSame(
            'You will have to bear the direct cost of returning the goods.',
            $instructions->returnCost
        );
    }

    #[Test]
    public function theGermanGoodsInstructionsUseTheOfficialWording(): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);

        $instructions = $subject->build($this->settings(), WithdrawalInstructionVariant::MULTIPLE_GOODS, 'https://shop.example/withdraw', 'de');

        $this->assertSame('Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.', $instructions->right);
        $this->assertSame(
            'Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die letzte Ware in Besitz genommen haben bzw. hat.',
            $instructions->period
        );
        $this->assertStringStartsWith('Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen', $instructions->effects);
        $this->assertSame(
            'Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist.',
            $instructions->withholding
        );
        $this->assertStringContainsString('Telefon: +49 30 1234, E-Mail: widerruf@shop.example', (string)$instructions->exercise);
    }

    #[Test]
    public function digitalContentRunsFromTheConclusionOfTheContractAndHasNoReturnSection(): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);

        $english = $subject->build($this->settings(), WithdrawalInstructionVariant::DIGITAL_CONTENT, '', 'en');
        $german = $subject->build($this->settings(), WithdrawalInstructionVariant::DIGITAL_CONTENT, '', 'de');

        $this->assertSame('The withdrawal period will expire after 14 days from the day of the conclusion of the contract.', $english->period);
        $this->assertSame('Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsabschlusses.', $german->period);
        $this->assertNull($english->withholding);
        $this->assertNull($english->returnInstruction);
        $this->assertNull($english->returnCost);
        $this->assertNull($english->diminishedValue);
    }

    #[Test]
    public function theOnlineFunctionIsOnlyAnnouncedWhenItsAddressIsKnown(): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);

        $with = $subject->build($this->settings(), WithdrawalInstructionVariant::GOODS, 'https://shop.example/withdraw', 'en');
        $without = $subject->build($this->settings(), WithdrawalInstructionVariant::GOODS, '', 'en');

        $this->assertStringContainsString('You can also exercise your right of withdrawal online at https://shop.example/withdraw.', (string)$with->onlineFunction);
        $this->assertStringContainsString('including its content and the date and time of its submission', (string)$with->onlineFunction);
        $this->assertNull($without->onlineFunction);
        $this->assertNull($without->onlineForm);
    }

    #[Test]
    public function aShopThatBearsTheReturnCostSaysSoAndNamesTheReturnAddress(): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);
        $settings = new WithdrawalSettings(
            returnCostBorneByTrader: true,
            trader: new TraderIdentity(name: 'Shop GmbH', address: 'Main Street 1', returnAddress: "Returns Dock 5\n54321 Othertown"),
        );

        $instructions = $subject->build($settings, WithdrawalInstructionVariant::GOODS, '', 'en');

        $this->assertSame('We will bear the cost of returning the goods.', $instructions->returnCost);
        $this->assertStringContainsString('Returns Dock 5, 54321 Othertown', (string)$instructions->returnInstruction);
    }

    #[Test]
    public function theModelFormIsAddressedToTheTraderAndKeepsTheOfficialLines(): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);

        $english = $subject->build($this->settings(), WithdrawalInstructionVariant::GOODS, '', 'en')->form;
        $german = $subject->build($this->settings(), WithdrawalInstructionVariant::GOODS, '', 'de')->form;

        $this->assertSame('To Shop GmbH, Main Street 1, 12345 Sampletown, e-mail: widerruf@shop.example:', $english->to);
        $this->assertSame('I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*)/for the provision of the following service (*),', $english->notice);
        $this->assertSame('Ordered on (*)/received on (*),', $english->ordered);
        $this->assertSame('An Shop GmbH, Main Street 1, 12345 Sampletown, E-Mail: widerruf@shop.example:', $german->to);
        $this->assertSame('Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)/die Erbringung der folgenden Dienstleistung (*)', $german->notice);
        $this->assertSame('Muster-Widerrufsformular', $german->heading);
    }

    #[Test]
    public function aMissingTraderConfigurationShowsAVisiblePlaceholder(): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);

        $instructions = $subject->build(new WithdrawalSettings(), WithdrawalInstructionVariant::GOODS, '', 'en');

        $this->assertStringContainsString('[name, geographical address, telephone number and email address of the trader]', $instructions->exercise);
    }

    /**
     * @param WithdrawalExclusion[] $exclusions
     */
    #[Test]
    #[DataProvider('variantProvider')]
    public function theVariantFollowsTheContentsOfTheOrder(array $exclusions, WithdrawalInstructionVariant $expected): void
    {
        $subject = $this->get(WithdrawalInstructionsBuilder::class);
        $order = new Order();
        foreach ($exclusions as $exclusion) {
            $item = new OrderItem();
            $item->setWithdrawalExclusion($exclusion);
            $order->getItems()->attach($item);
        }

        $this->assertSame($expected, $subject->variantFor($order));
    }

    public static function variantProvider(): \Generator
    {
        yield 'one good' => ['exclusions' => [WithdrawalExclusion::NONE], 'expected' => WithdrawalInstructionVariant::GOODS];
        yield 'several goods' => ['exclusions' => [WithdrawalExclusion::NONE, WithdrawalExclusion::PERSONALIZED], 'expected' => WithdrawalInstructionVariant::MULTIPLE_GOODS];
        yield 'only digital content' => ['exclusions' => [WithdrawalExclusion::DIGITAL_CONTENT], 'expected' => WithdrawalInstructionVariant::DIGITAL_CONTENT];
        yield 'one good and digital content' => ['exclusions' => [WithdrawalExclusion::NONE, WithdrawalExclusion::DIGITAL_CONTENT], 'expected' => WithdrawalInstructionVariant::GOODS];
        yield 'an order without items' => ['exclusions' => [], 'expected' => WithdrawalInstructionVariant::GOODS];
    }

    private function settings(): WithdrawalSettings
    {
        return new WithdrawalSettings(
            trader: new TraderIdentity(
                name: 'Shop GmbH',
                address: "Main Street 1\n12345 Sampletown",
                email: 'widerruf@shop.example',
                phone: '+49 30 1234',
            ),
        );
    }
}
