<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Express\Exception\EmptyExpressBasketException;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Core\Service\Order\OrderFinalizationService;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Turns a confirmed express wallet payment into an order. It is the express counterpart of
 * {@see OrderPlacementService}: the address comes from the wallet rather than the form, there is no
 * interactive terms step, and the payment is already settled - the wallet token was authorized inline, so
 * the settled result is passed in rather than initiated.
 *
 * It reuses the exact {@see OrderCreationService} and {@see OrderFinalizationService} normal checkout runs
 * on, in one transaction, so stock, numbering, loyalty and basket clearing behave identically - the single
 * source of truth the express seam is built around. Unlike the shipping-rate callback it runs in a normal
 * in-page request, so the full configuration is available.
 */
#[Autoconfigure(public: true)]
final readonly class ExpressOrderService
{
    public function __construct(
        private PriceQuoteService $priceQuoteService,
        private ProductsConfigurationFactory $configurationFactory,
        private OrderCreationService $orderCreationService,
        private OrderFinalizationService $orderFinalizationService,
        private ConnectionPool $connectionPool
    ) {}

    public function place(
        ServerRequestInterface $request,
        ExpressCheckoutProviderInterface $provider,
        BasketViewModel $liveBasketViewModel,
        Address $address,
        string $shippingOptionKey,
        PaymentResult $paymentResult
    ): Order {
        $configuration = $this->configurationFactory->create($request);
        $basketViewModel = $this->priceQuoteService->resolve($request, $liveBasketViewModel, $configuration);
        if ($basketViewModel->getItems() === []) {
            throw new EmptyExpressBasketException('Cannot place an express order for an empty basket.', 1784220769);
        }

        $selections = new CheckoutSelections($shippingOptionKey);
        $descriptor = new ExpressPaymentDescriptor($provider);
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_order');
        $connection->beginTransaction();
        try {
            $order = $this->orderCreationService->create($request, $basketViewModel, $selections, $address, $descriptor);
            $this->orderFinalizationService->finalize($order, $paymentResult, $request);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $order;
    }
}
