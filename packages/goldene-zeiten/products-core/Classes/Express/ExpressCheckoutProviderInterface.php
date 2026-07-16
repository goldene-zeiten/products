<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A one-step "express checkout" button that opens a wallet sheet on the cart or product page, where the
 * wallet supplies the address and shipping is computed live before an order exists. This inverts the
 * normal checkout order, so it is a separate seam from {@see PaymentMethodInterface} (which starts only
 * after an order and a chosen shipping option exist).
 *
 * Core owns the shared machinery every provider reuses - the signed basket token, the live shipping-rate
 * quote and the express order creation - so an implementation only declares its availability and hands
 * the frontend the client-side configuration its own button JS needs.
 */
#[AutoconfigureTag('products.express_checkout_provider')]
interface ExpressCheckoutProviderInterface
{
    public function getIdentifier(): string;

    public function getLabel(): string;

    /**
     * Discovery phase: may this express button be offered for the given basket, currency and customer?
     */
    public function isAvailable(ExpressCheckoutContext $context): bool;

    /**
     * Higher priority is rendered first. Providers sharing a priority keep their registration order.
     */
    public function getPriority(): int;

    /**
     * The client-side configuration the frontend needs to render this provider's express button -
     * publishable keys, the wallets to surface, button options. Wallet-agnostic key-value data the
     * provider's own JS consumes; core neither interprets nor stores it.
     *
     * @return array<string, mixed>
     */
    public function getButtonConfiguration(ExpressCheckoutContext $context): array;
}
