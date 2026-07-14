<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Discount\Voucher;

use GoldeneZeiten\Products\Core\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Core\Service\Checkout\CheckoutStateStore;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The voucher feature's own slice of the checkout state: the codes the customer applied. It owns the
 * shape of that slice, so the basket no longer carries a voucher field, and moving the voucher into its
 * own extension takes this with it.
 */
final class VoucherCheckoutState
{
    private const PAYLOAD_KEY = 'codes';

    public function __construct(
        private readonly CheckoutStateStore $checkoutStateStore
    ) {}

    /**
     * @return string[]
     */
    public function getCodes(ServerRequestInterface $request): array
    {
        $codes = $this->checkoutStateStore->getPayload($request, CoreAdjustmentProvider::VOUCHER)[self::PAYLOAD_KEY] ?? [];

        return is_array($codes) ? array_values(array_map('strval', $codes)) : [];
    }

    public function addCode(ServerRequestInterface $request, string $code): void
    {
        $codes = $this->getCodes($request);
        if (!in_array($code, $codes, true)) {
            $codes[] = $code;
        }
        $this->store($request, $codes);
    }

    public function removeCode(ServerRequestInterface $request, string $code): void
    {
        $this->store($request, array_values(array_filter(
            $this->getCodes($request),
            static fn(string $existing): bool => $existing !== $code
        )));
    }

    public function clearCodes(ServerRequestInterface $request): void
    {
        $this->store($request, []);
    }

    /**
     * @param string[] $codes
     */
    private function store(ServerRequestInterface $request, array $codes): void
    {
        $this->checkoutStateStore->setPayload($request, CoreAdjustmentProvider::VOUCHER, [self::PAYLOAD_KEY => $codes]);
    }
}
