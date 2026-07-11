<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Order\OrderFinalizationService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\TestMailer;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Regression coverage for a real (fixed) bug: OrderFinalizationService::finalize() unconditionally
 * re-dispatched events (and therefore re-sent confirmation emails, re-cleared the basket, ...) on
 * every call - latent today since redirect/webhook payment methods aren't wired in yet, but would
 * double-process the moment a real gateway calls back twice (sync return + async webhook).
 */
final class OrderFinalizationServiceIdempotencyTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/frontend-test',
    ];

    private OrderFinalizationService $subject;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_finalization_idempotency.csv');
        // BasketService's pricing-chain dependencies read Extbase settings eagerly in their
        // constructors, which requires a request resolvable via $GLOBALS['TYPO3_REQUEST'] outside
        // a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->subject = $this->get(OrderFinalizationService::class);
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        self::assertNotNull($order);
        $this->order = $order;
    }

    #[Test]
    public function finalizeTransitionsAPendingOrderToConfirmed(): void
    {
        $this->subject->finalize($this->order, PaymentResult::completed(PaymentStatus::PAID), $this->request());

        self::assertSame(OrderStatus::CONFIRMED, $this->order->getStatus());
        // One AfterOrderFinalizedEvent confirmation mail + one OrderStatusChangedEvent mail
        // (the pending->confirmed transition itself).
        self::assertCount(2, TestMailer::getSentEmails());
    }

    #[Test]
    public function finalizeIsANoOpWhenCalledAgainForAnAlreadyFinalizedOrder(): void
    {
        $this->subject->finalize($this->order, PaymentResult::completed(PaymentStatus::PAID), $this->request());
        $this->subject->finalize($this->order, PaymentResult::completed(PaymentStatus::PAID), $this->request());

        self::assertSame(OrderStatus::CONFIRMED, $this->order->getStatus());
        // The second call must not re-dispatch anything - still exactly the first call's mails.
        self::assertCount(2, TestMailer::getSentEmails());
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
