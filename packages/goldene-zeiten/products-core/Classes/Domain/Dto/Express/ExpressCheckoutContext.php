<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Express;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The state an express-checkout provider decides its availability and initial button from: the basket
 * total and currency, and the customer if logged in. Unlike {@see PaymentContext} there is no address or
 * chosen shipping yet - express checkout opens on the cart or product page, before either exists; the
 * wallet supplies the address and shipping is computed live afterwards.
 */
#[Exclude]
final readonly class ExpressCheckoutContext
{
    public function __construct(
        private Money $amount,
        private string $currency,
        private int $frontendUserUid = 0,
        private string $countryCode = ''
    ) {}

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getFrontendUserUid(): int
    {
        return $this->frontendUserUid;
    }

    /**
     * The shop's default/guessed country, if any - the real destination arrives from the wallet.
     */
    public function getCountryCode(): string
    {
        return $this->countryCode;
    }
}
