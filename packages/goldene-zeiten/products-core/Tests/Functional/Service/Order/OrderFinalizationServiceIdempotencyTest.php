<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Discount\Voucher\VoucherCheckoutState;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderFinalizationService;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Regression: finalize() must be idempotent and not re-dispatch events on retry.
 */
final class OrderFinalizationServiceIdempotencyTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
        'goldene-zeiten/frontend-test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderFinalizationServiceIdempotencyTest/order_finalization_idempotency.csv');
    }

    #[Test]
    public function finalizeTransitionsAPendingOrderToConfirmed(): void
    {
        $subject = $this->get(OrderFinalizationService::class);
        $order = $this->fetchOrder();

        $subject->finalize($order, PaymentResult::completed(PaymentStatus::PAID), $this->request());

        $this->assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        // One finalization + one status-changed mail.
        $this->assertCount(2, TestMailer::getSentEmails());
    }

    #[Test]
    public function finalizeIsANoOpWhenCalledAgainForAnAlreadyFinalizedOrder(): void
    {
        $order = $this->fetchOrder();
        $subject = $this->get(OrderFinalizationService::class);

        $subject->finalize($order, PaymentResult::completed(PaymentStatus::PAID), $this->request());
        $subject->finalize($order, PaymentResult::completed(PaymentStatus::PAID), $this->request());

        $this->assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        $this->assertCount(2, TestMailer::getSentEmails());
    }

    #[Test]
    public function finalizeClearsAppliedVoucherCodesSoTheyDoNotLeakIntoTheNextOrder(): void
    {
        $subject = $this->get(OrderFinalizationService::class);
        $voucherState = $this->get(VoucherCheckoutState::class);
        $request = $this->request();
        $voucherState->addCode($request, 'SAVE10');

        $subject->finalize($this->fetchOrder(), PaymentResult::completed(PaymentStatus::PAID), $request);

        $this->assertSame([], $voucherState->getCodes($request));
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertNotNull($order);
        return $order;
    }

    private function request(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
