<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Who the consumer addresses a withdrawal to, as named in the Annex I(A) instructions and the
 * Annex I(B) model form of Directive 2011/83/EU. Built from the site settings.
 */
#[Exclude]
final readonly class TraderIdentity
{
    public function __construct(
        public string $name = '',
        public string $address = '',
        public string $email = '',
        public string $phone = '',
        public string $returnAddress = '',
    ) {}

    /**
     * Where returned goods go: the dedicated return address, else the trader's own.
     */
    public function getEffectiveReturnAddress(): string
    {
        return $this->returnAddress !== '' ? $this->returnAddress : $this->address;
    }

    /**
     * @return string[]
     */
    public function getAddressLines(): array
    {
        return $this->splitLines($this->address);
    }

    /**
     * @return string[]
     */
    public function getReturnAddressLines(): array
    {
        return $this->splitLines($this->getEffectiveReturnAddress());
    }

    /**
     * @return string[]
     */
    private function splitLines(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (trim($line) !== '') {
                $lines[] = trim($line);
            }
        }
        return $lines;
    }
}
