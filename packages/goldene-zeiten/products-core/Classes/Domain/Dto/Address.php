<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class Address
{
    public function __construct(
        private string $email = '',
        private string $salutation = '',
        private string $firstName = '',
        private string $lastName = '',
        private string $company = '',
        private string $street = '',
        private string $zip = '',
        private string $city = '',
        private string $country = ''
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }
}
