<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Checkout;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutFieldConfiguration;
use GoldeneZeiten\Products\Core\Domain\Enum\FieldRequirement;
use GoldeneZeiten\Products\Core\Service\Checkout\AddressCompletenessChecker;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class AddressFieldRulesTest extends AbstractFunctionalTestCase
{
    #[Test]
    #[DataProvider('invalidAddressProvider')]
    public function fieldsAreValidatedAgainstTheShopsFieldConfiguration(Address $address, string $expectedInvalidField): void
    {
        $subject = $this->get(AddressCompletenessChecker::class);
        $fields = new CheckoutFieldConfiguration(
            [
                'salutation' => FieldRequirement::Optional,
                'company' => FieldRequirement::Hidden,
                'telephone' => FieldRequirement::Required,
                'vatId' => FieldRequirement::Optional,
            ],
            ['DE', 'AT'],
            ['mr', 'ms'],
            false,
        );

        $this->assertContains($expectedInvalidField, $subject->getInvalidFields($address, true, $fields));
    }

    public static function invalidAddressProvider(): \Generator
    {
        yield 'required telephone missing' => [
            'address' => self::completeAddress(telephone: ''),
            'expectedInvalidField' => 'telephone',
        ];
        yield 'country outside the allowed countries' => [
            'address' => self::completeAddress(country: 'CH'),
            'expectedInvalidField' => 'country',
        ];
        yield 'salutation outside the configured salutations' => [
            'address' => self::completeAddress(salutation: 'dr'),
            'expectedInvalidField' => 'salutation',
        ];
    }

    #[Test]
    public function optionalAndHiddenFieldsMayStayEmpty(): void
    {
        $subject = $this->get(AddressCompletenessChecker::class);
        $fields = new CheckoutFieldConfiguration(
            [
                'salutation' => FieldRequirement::Optional,
                'company' => FieldRequirement::Hidden,
                'telephone' => FieldRequirement::Optional,
                'vatId' => FieldRequirement::Optional,
            ],
            ['DE'],
            ['mr', 'ms'],
            false,
        );

        $this->assertSame([], $subject->getInvalidFields(self::completeAddress(telephone: '', salutation: ''), true, $fields));
    }

    #[Test]
    public function withoutAFieldConfigurationOnlyTheBaseFieldsAreRequired(): void
    {
        $subject = $this->get(AddressCompletenessChecker::class);

        $this->assertSame([], $subject->getInvalidFields(self::completeAddress(telephone: '', country: 'CH')));
    }

    private static function completeAddress(string $telephone = '+49 30 1', string $country = 'DE', string $salutation = 'ms'): Address
    {
        return new Address(
            email: 'buyer@example.com',
            salutation: $salutation,
            firstName: 'Jane',
            lastName: 'Doe',
            street: 'Main Street 1',
            zip: '10115',
            city: 'Berlin',
            country: $country,
            telephone: $telephone,
        );
    }
}
