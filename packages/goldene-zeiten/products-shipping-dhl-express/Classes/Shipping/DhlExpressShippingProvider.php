<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfigurationFactory;
use GoldeneZeiten\Products\Shipping\DhlExpress\Domain\Dto\DhlExpressRate;
use GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressShippingOptionsEvent;
use GoldeneZeiten\Products\Shipping\DhlExpress\Rating\DhlExpressRatingClient;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * A real (non-fallback) carrier that offers live DHL Express rates. Being a real carrier, it supersedes
 * the shop's built-in table-rate shipping whenever it returns options; when it cannot - unconfigured, DHL
 * unreachable, or no rate for the basket - it returns none, and the table-rate fallback takes over, so the
 * checkout never dead-ends.
 */
final class DhlExpressShippingProvider implements ShippingProviderInterface
{
    public const IDENTIFIER = 'dhl';

    public function __construct(
        private readonly DhlExpressConfigurationFactory $configurationFactory,
        private readonly DhlExpressRatingClient $ratingClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getPriority(): int
    {
        return 100;
    }

    /**
     * @return ShippingOption[]
     */
    public function quote(ShippingContext $context): array
    {
        $configuration = $this->configurationFactory->forCurrentRequest();
        if (!$configuration->isComplete()) {
            return [];
        }

        try {
            $rates = $this->ratingClient->rate($context, $configuration);
        } catch (\Throwable $exception) {
            $this->logger->error('DHL rating failed; leaving the basket to the fallback carrier.', ['exception' => $exception]);
            return [];
        }

        return $this->toOptions($rates, $context, $configuration);
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        foreach ($this->quote($context) as $option) {
            if ($option->getOptionIdentifier() === $optionIdentifier) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param DhlExpressRate[] $rates
     * @return ShippingOption[]
     */
    private function toOptions(array $rates, ShippingContext $context, DhlExpressConfiguration $configuration): array
    {
        $options = [];
        foreach ($rates as $rate) {
            if ($this->isOffered($rate, $context, $configuration)) {
                $options[] = $this->toOption($rate);
            }
        }

        $event = new ModifyDhlExpressShippingOptionsEvent($options, $context, $configuration);
        $this->eventDispatcher->dispatch($event);

        return $event->getOptions();
    }

    private function isOffered(DhlExpressRate $rate, ShippingContext $context, DhlExpressConfiguration $configuration): bool
    {
        if (!$configuration->offersProduct($rate->productCode)) {
            return false;
        }
        if ($rate->currencyCode !== '' && $rate->currencyCode !== $context->getCurrency()) {
            // Never present a rate quoted in another currency as if it were the basket's own.
            $this->logger->warning('DHL rate currency differs from the basket currency; skipping the product.', [
                'product' => $rate->productCode,
                'rateCurrency' => $rate->currencyCode,
                'basketCurrency' => $context->getCurrency(),
            ]);
            return false;
        }

        return true;
    }

    private function toOption(DhlExpressRate $rate): ShippingOption
    {
        return new ShippingOption(
            self::IDENTIFIER,
            $rate->productCode,
            $rate->productName !== '' ? $rate->productName : sprintf('DHL %s', $rate->productCode),
            Money::fromDecimalString($rate->amount),
            null,
            $rate->estimatedDelivery !== '' ? 'Estimated delivery ' . substr($rate->estimatedDelivery, 0, 10) : '',
        );
    }
}
