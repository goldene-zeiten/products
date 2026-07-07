<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller\Backend;

use GoldeneZeiten\Products\Backend\OrderListFilter;
use GoldeneZeiten\Products\Backend\OrderManagementRepository;
use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Payment\RefundablePaymentMethodInterface;
use GoldeneZeiten\Products\Service\Order\Exception\InvalidOrderStatusTransitionException;
use GoldeneZeiten\Products\Service\Order\Exception\InvalidPaymentStatusTransitionException;
use GoldeneZeiten\Products\Service\Order\OrderStatusManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * "Products Order" backend module: a filtered order list plus a detail view with manual
 * status/payment-status transitions on top of the existing OrderStatusManager - the module is a
 * UI on that service, not a new state machine.
 */
#[AsController]
final class OrderManagementModuleController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly OrderManagementRepository $orderRepository,
        private readonly OrderStatusManager $orderStatusManager,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->translate('module.products_order.title'));
        if ($request->getMethod() === 'POST') {
            $this->handlePostedAction($request, $moduleTemplate);
        }
        $orderUid = (int)($request->getQueryParams()['order'] ?? 0);
        $moduleTemplate->assignMultiple(
            $orderUid > 0 ? $this->buildDetailView($orderUid, $request) : $this->buildListView($request)
        );
        return $moduleTemplate->renderResponse('Backend/OrderManagement/Main');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListView(ServerRequestInterface $request): array
    {
        $filter = $this->buildFilterFromRequest($request);
        return [
            'mode' => 'list',
            'filter' => $filter,
            'orders' => $this->orderRepository->fetchFiltered($filter),
            'statusOptions' => OrderStatus::cases(),
            'detailUrl' => (string)$this->uriBuilder->buildUriFromRequest($request, []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDetailView(int $orderUid, ServerRequestInterface $request): array
    {
        $order = $this->orderRepository->fetchRow($orderUid);
        return [
            'mode' => 'detail',
            'order' => $order,
            'availableStatusTransitions' => $order !== null ? $this->availableStatusTransitions($order['status']) : [],
            'canMarkPaid' => $order !== null && $this->canMarkPaid($order['paymentStatus']),
            'canRefund' => $order !== null && $this->canRefund($order['paymentMethod'], $order['paymentStatus']),
            'listUrl' => (string)$this->uriBuilder->buildUriFromRequest($request, ['order']),
        ];
    }

    private function canRefund(string $paymentMethodIdentifier, string $paymentStatusValue): bool
    {
        $status = PaymentStatus::tryFrom($paymentStatusValue);
        if ($status === null || !$status->canTransitionTo(PaymentStatus::REFUNDED)) {
            return false;
        }
        try {
            return $this->paymentMethodRegistry->get($paymentMethodIdentifier) instanceof RefundablePaymentMethodInterface;
        } catch (PaymentMethodNotFoundException) {
            return false;
        }
    }

    /**
     * @return OrderStatus[]
     */
    private function availableStatusTransitions(string $statusValue): array
    {
        $current = OrderStatus::tryFrom($statusValue);
        if ($current === null) {
            return [];
        }
        return array_values(array_filter(OrderStatus::cases(), $current->canTransitionTo(...)));
    }

    private function canMarkPaid(string $paymentStatusValue): bool
    {
        $current = PaymentStatus::tryFrom($paymentStatusValue);
        return $current !== null && $current->canTransitionTo(PaymentStatus::PAID);
    }

    private function buildFilterFromRequest(ServerRequestInterface $request): OrderListFilter
    {
        $query = $request->getQueryParams();
        return new OrderListFilter(
            status: $this->stringOrNull($query['status'] ?? null),
            orderNumber: $this->stringOrNull($query['orderNumber'] ?? null),
            email: $this->stringOrNull($query['email'] ?? null),
            dateFrom: $this->dateOrNull($query['dateFrom'] ?? null),
            dateTo: $this->dateOrNull($query['dateTo'] ?? null),
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function dateOrNull(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false ? $date : null;
    }

    private function handlePostedAction(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): void
    {
        $body = (array)$request->getParsedBody();
        $order = $this->orderRepository->findForEditing((int)($body['orderUid'] ?? 0));
        if ($order === null) {
            return;
        }
        try {
            $this->applyAction($order, (string)($body['action'] ?? ''), $body);
            $this->orderRepository->persist($order);
            $moduleTemplate->addFlashMessage($this->translate('message.order_updated'));
        } catch (InvalidOrderStatusTransitionException|InvalidPaymentStatusTransitionException|PaymentMethodNotFoundException $exception) {
            $moduleTemplate->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyAction(Order $order, string $action, array $body): void
    {
        if ($action === 'markPaid') {
            $this->orderStatusManager->transitionPayment($order, PaymentStatus::PAID);
            return;
        }
        if ($action === 'refund') {
            $this->applyRefund($order);
            return;
        }
        if ($action === 'transition') {
            $target = OrderStatus::tryFrom((string)($body['targetStatus'] ?? ''));
            if ($target !== null) {
                $this->orderStatusManager->transition($order, $target);
            }
        }
    }

    private function applyRefund(Order $order): void
    {
        $paymentMethod = $this->paymentMethodRegistry->get($order->getPaymentMethod());
        if ($paymentMethod instanceof RefundablePaymentMethodInterface) {
            $paymentMethod->refund($order, $order->getTotalGross());
            $this->orderStatusManager->transitionPayment($order, PaymentStatus::REFUNDED);
        }
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:' . $key);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
