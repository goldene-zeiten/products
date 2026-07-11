<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Withdrawal;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalService;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\TestMailer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

final class WithdrawalServiceTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
        // WithdrawalService reads Extbase settings eagerly in its constructor, which requires a
        // request resolvable via $GLOBALS['TYPO3_REQUEST'] outside a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    public function resolveOrderReturnsTheOrderForAValidToken(): void
    {
        $order = $this->fetchOrder();
        $token = $this->get(WithdrawalTokenService::class)->generateToken($order);

        $resolved = $this->get(WithdrawalService::class)->resolveOrder(1, $token);

        $this->assertSame($order->getUid(), $resolved->getUid());
    }

    #[Test]
    public function resolveOrderThrowsForAnInvalidToken(): void
    {
        $this->expectException(InvalidWithdrawalTokenException::class);
        $this->expectExceptionCode(1752100000);

        $this->get(WithdrawalService::class)->resolveOrder(1, 'not-a-valid-token');
    }

    #[Test]
    public function isStillWithdrawableIsTrueWithinTheDefaultPeriodForACancellableStatus(): void
    {
        $order = $this->fetchOrder();
        $order->setOrderDate(new \DateTime());

        $this->assertTrue($this->get(WithdrawalService::class)->isStillWithdrawable($order));
    }

    #[Test]
    public function isStillWithdrawableIsFalseAfterThePeriodExpired(): void
    {
        $order = $this->fetchOrder();
        $order->setOrderDate((new \DateTime())->modify('-30 days'));

        $this->assertFalse($this->get(WithdrawalService::class)->isStillWithdrawable($order));
    }

    #[Test]
    public function isStillWithdrawableIsFalseForANonCancellableStatus(): void
    {
        $order = $this->fetchOrder();
        $order->setOrderDate(new \DateTime());
        $order->setStatus(OrderStatus::SHIPPED);

        $this->assertFalse($this->get(WithdrawalService::class)->isStillWithdrawable($order));
    }

    #[Test]
    public function withdrawCancelsTheOrderAndNotifiesCustomerAndMerchant(): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $order = $this->fetchOrder();
        $order->setOrderDate(new \DateTime());

        $this->get(WithdrawalService::class)->withdraw($order, 'shopper@example.com', 'Changed my mind');

        $this->assertSame(OrderStatus::CANCELLED, $order->getStatus());
        $this->assertCount(1, $order->getStatusLog());
        $this->assertSame('Changed my mind', $order->getStatusLog()[0]['note']);
        $recipients = array_map(static fn($email): string => $email->getTo()[0]->getAddress(), TestMailer::getSentEmails());
        $this->assertContains('merchant@example.com', $recipients);
        $this->assertContains('shopper@example.com', $recipients);
    }

    #[Test]
    public function withdrawThrowsWhenTheEmailDoesNotMatch(): void
    {
        $order = $this->fetchOrder();

        $this->expectException(WithdrawalEmailMismatchException::class);
        $this->expectExceptionCode(1752100001);

        $this->get(WithdrawalService::class)->withdraw($order, 'someone-else@example.com', '');
    }

    #[Test]
    public function withdrawThrowsWhenThePeriodHasExpired(): void
    {
        $order = $this->fetchOrder();
        $order->setOrderDate((new \DateTime())->modify('-30 days'));

        $this->expectException(WithdrawalPeriodExpiredException::class);
        $this->expectExceptionCode(1752100002);

        $this->get(WithdrawalService::class)->withdraw($order, 'shopper@example.com', '');
    }

    #[Test]
    public function withdrawThrowsWhenTheOrderIsNoLongerCancellable(): void
    {
        $order = $this->fetchOrder();
        $order->setOrderDate(new \DateTime());
        $order->setStatus(OrderStatus::SHIPPED);

        $this->expectException(OrderNotWithdrawableException::class);
        $this->expectExceptionCode(1752100003);

        $this->get(WithdrawalService::class)->withdraw($order, 'shopper@example.com', '');
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
