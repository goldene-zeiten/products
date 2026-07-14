..  include:: /Includes.rst.txt
..  _developer-api-loyalty:

============
Loyalty API
============

The Loyalty API enables integrators to implement a loyalty programme - points, cashback, tiered
rewards - that a customer both **spends on** an order and **earns from** it. That round trip is what
distinguishes loyalty from a discount: a discount only ever lowers a total, while a loyalty programme
also gives something back once the order exists.

Loyalty is on-top functionality. The extension ships the credit-points programme as one such provider,
but a shop with no loyalty provider at all still checks out.

**Location:** :php:`GoldeneZeiten\Products\Core\Loyalty\LoyaltyProviderInterface`

The Lifecycle
=============

The contract has four steps, and each runs at a different moment of the checkout:

..  code-block:: php

    #[AutoconfigureTag('products.loyalty_provider')]
    interface LoyaltyProviderInterface
    {
        public function getIdentifier(): string;

        // Display: what the customer has to spend.
        public function getBalance(LoyaltyContext $context): int;

        // Before placement: refuse a spend the customer cannot cover.
        public function assertRedeemable(LoyaltyContext $context): void;

        // Read-only: what the requested spend is worth against this order.
        public function quoteRedemption(LoyaltyContext $context): ?CheckoutAdjustment;

        // Inside the order transaction: debit the spend and book it.
        public function applyRedemption(Order $order, LoyaltyContext $context): void;

        // Once the order exists: credit what it earned.
        public function award(Order $order, LoyaltyContext $context): void;
    }

:php:`getBalance()`
    Called when the checkout renders, to show the customer what they may spend. Return ``0`` for a
    programme that does not apply to this customer.

:php:`assertRedeemable()`
    Called **before** the order is created, so an unaffordable spend fails before anything is written.
    Throw when the requested spend exceeds what the customer has. Do nothing when nothing was requested.

:php:`quoteRedemption()`
    Read-only, and safe to call whenever a total has to be shown. Return the spend as a
    :php:`AdjustmentType::LOYALTY` adjustment with a **negative** amount, or ``null`` when the customer
    spends nothing. The adjustment flows through the same totals pipeline as every other money effect -
    see :ref:`developer-api-checkout-adjustments`.

:php:`applyRedemption()`
    The debit, and the write that must happen exactly once. It runs **inside** the order transaction, so
    a failure later in the placement unwinds it along with the order.

:php:`award()`
    The earn side. It runs once the order exists, and is where the programme credits whatever the order
    was worth.

The Context
===========

:php:`GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext` is immutable and carries everything a
programme needs for both directions:

..  code-block:: php

    final readonly class LoyaltyContext
    {
        public function __construct(
            private ServerRequestInterface $request,
            private BasketViewModel $basketViewModel,
            private Money $remainingGoodsTotal,
            private int $frontendUserUid,
            private int $requestedSpendPoints = 0
        ) {}
    }

:php:`getRemainingGoodsTotal()`
    What is left **after discounts**. Points are therefore spent against what the customer still owes,
    not against the pre-discount total.

:php:`getRequest()`
    The request travels with the context on purpose: how much a point is worth, and how points are
    earned, is the programme's own configuration. The extension cannot resolve that on a programme's
    behalf, so the programme resolves it itself rather than the core having to know what each one needs.

Registration
============

Implement the interface. The :php:`#[AutoconfigureTag('products.loyalty_provider')]` sits on the
*interface*, so an implementation is collected automatically with no :file:`Services.yaml` entry, given
the usual ``autoconfigure``. :php:`GoldeneZeiten\Products\Core\Loyalty\LoyaltyRegistry` collects the providers
via :php:`#[TaggedIterator]` and serves each step of the lifecycle to the checkout.

The interface is the registration mechanism. There is no loyalty event, because an event cannot answer
"what is this customer's balance?", cannot refuse a placement, and cannot take part in the order
transaction.

The Bundled Programme
=====================

:php:`GoldeneZeiten\Products\Core\Loyalty\CreditPointsLoyaltyProvider` is the credit-points programme, and it
is nothing more than an implementation of this interface. The checkout reaches it only through the
registry, which is what allows it to move into its own extension without the core changing.

When the programme is disabled it does nothing across **every** method - no balance, no adjustment, no
debit and no award.

Example
=======

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Loyalty;

    use GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext;
    use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
    use GoldeneZeiten\Products\Core\Domain\Model\Order;
    use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
    use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
    use GoldeneZeiten\Products\Core\Loyalty\LoyaltyProviderInterface;
    use MyVendor\MyExtension\Service\CashbackAccount;

    /**
     * A cashback wallet: one cent spent is one cent off, and every order earns 2% back.
     */
    final class CashbackLoyaltyProvider implements LoyaltyProviderInterface
    {
        private const EARN_PERCENT = 2;

        public function __construct(
            private readonly CashbackAccount $account
        ) {}

        public function getIdentifier(): string
        {
            return 'cashback';
        }

        public function getBalance(LoyaltyContext $context): int
        {
            return $this->account->getBalance($context->getFrontendUserUid());
        }

        public function assertRedeemable(LoyaltyContext $context): void
        {
            $requested = $context->getRequestedSpendPoints();
            if ($requested <= 0) {
                return;
            }
            if ($requested > $this->getBalance($context)) {
                throw new CashbackNotAffordableException(
                    sprintf('Requested %d cents of cashback but the wallet cannot cover it.', $requested),
                    1784073650
                );
            }
        }

        public function quoteRedemption(LoyaltyContext $context): ?CheckoutAdjustment
        {
            $spend = min($context->getRequestedSpendPoints(), $context->getRemainingGoodsTotal()->getCents());
            if ($spend <= 0) {
                return null;
            }

            return new CheckoutAdjustment(
                AdjustmentType::LOYALTY,
                'cashback',
                'Cashback',
                Money::fromCents(-$spend),
                0.0,
                ['spent' => (string)$spend]
            );
        }

        public function applyRedemption(Order $order, LoyaltyContext $context): void
        {
            $spend = min($context->getRequestedSpendPoints(), $context->getRemainingGoodsTotal()->getCents());
            if ($spend <= 0) {
                return;
            }
            $this->account->debit($context->getFrontendUserUid(), $spend, $order->getUid() ?? 0);
        }

        public function award(Order $order, LoyaltyContext $context): void
        {
            if ($context->getFrontendUserUid() === 0) {
                return;
            }
            $earned = (int)round($order->getTotalGross()->getCents() * self::EARN_PERCENT / 100);
            if ($earned > 0) {
                $this->account->credit($context->getFrontendUserUid(), $earned, $order->getUid() ?? 0);
            }
        }
    }

..  note::
    Both :php:`applyRedemption()` and :php:`award()` run inside the order transaction. Anything they
    write to the database is rolled back with the order if the placement fails afterwards; anything they
    send to an external system is not, so keep external calls out of them.
