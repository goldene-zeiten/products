<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * The maker of a product, as shown on its detail page and in its structured data. The name, the postal
 * address and the electronic address are what the EU General Product Safety Regulation (Art. 19) demands
 * of an online offer.
 */
#[Exclude]
class Manufacturer extends AbstractEntity
{
    protected string $name = '';
    protected string $brandName = '';
    protected string $website = '';
    protected string $email = '';
    protected string $contactUrl = '';
    protected string $street = '';
    protected string $zip = '';
    protected string $city = '';
    protected string $country = '';
    protected string $euResponsibleName = '';
    protected string $euResponsibleAddress = '';
    protected string $euResponsibleEmail = '';
    protected string $description = '';
    protected int $page = 0;
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $logo;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->logo = new ObjectStorage();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * The brand the products are sold under; the name when no separate brand is set.
     */
    public function getBrandName(): string
    {
        return $this->brandName !== '' ? $this->brandName : $this->name;
    }

    public function setBrandName(string $brandName): void
    {
        $this->brandName = $brandName;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function setWebsite(string $website): void
    {
        $this->website = $website;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getContactUrl(): string
    {
        return $this->contactUrl;
    }

    public function setContactUrl(string $contactUrl): void
    {
        $this->contactUrl = $contactUrl;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function setZip(string $zip): void
    {
        $this->zip = $zip;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    /**
     * The non-empty lines of the postal address: street, ZIP code with city. The country is
     * left to the template, which names it in the language of the page.
     *
     * @return string[]
     */
    public function getAddressLines(): array
    {
        $lines = [$this->street, trim($this->zip . ' ' . $this->city)];
        return array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    public function getEuResponsibleName(): string
    {
        return $this->euResponsibleName;
    }

    public function setEuResponsibleName(string $euResponsibleName): void
    {
        $this->euResponsibleName = $euResponsibleName;
    }

    public function getEuResponsibleAddress(): string
    {
        return $this->euResponsibleAddress;
    }

    public function setEuResponsibleAddress(string $euResponsibleAddress): void
    {
        $this->euResponsibleAddress = $euResponsibleAddress;
    }

    public function getEuResponsibleEmail(): string
    {
        return $this->euResponsibleEmail;
    }

    public function setEuResponsibleEmail(string $euResponsibleEmail): void
    {
        $this->euResponsibleEmail = $euResponsibleEmail;
    }

    public function isEuResponsibleSet(): bool
    {
        return $this->euResponsibleName !== '' || $this->euResponsibleAddress !== '' || $this->euResponsibleEmail !== '';
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getLogo(): ?FileReference
    {
        foreach ($this->logo as $logo) {
            return $logo;
        }
        return null;
    }

    /**
     * @param ObjectStorage<FileReference> $logo
     */
    public function setLogo(ObjectStorage $logo): void
    {
        $this->logo = $logo;
    }
}
