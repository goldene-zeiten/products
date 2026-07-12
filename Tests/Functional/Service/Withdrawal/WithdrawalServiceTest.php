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
use PHPUnit\Framework\Attributes\DataProvider;
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
        // WithdrawalService reads $GLOBALS['TYPO3_REQUEST'] eagerly in its constructor.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    public function resolveOrderReturnsTheOrderForAValidToken(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder();
        $token = $this->get(WithdrawalTokenService::class)->generateToken($order);

        $resolved = $subject->resolveOrder(1, $token);

        $this->assertSame($order->getUid(), $resolved->getUid());
    }

    #[Test]
    public function resolveOrderThrowsForAnInvalidToken(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $this->expectException(InvalidWithdrawalTokenException::class);
        $this->expectExceptionCode(1752100000);

        $subject->resolveOrder(1, 'not-a-valid-token');
    }

    #[Test]
    #[DataProvider('isStillWithdrawableProvider')]
    public function isStillWithdrawableReflectsThePeriodAndStatus(string $orderDateModifier, ?OrderStatus $status, bool $expected): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder();
        $order->setOrderDate((new \DateTime())->modify($orderDateModifier));
        if ($status !== null) {
            $order->setStatus($status);
        }

        $this->assertSame($expected, $subject->isStillWithdrawable($order));
    }

    public static function isStillWithdrawableProvider(): \Generator
    {
        yield 'true within the default period for a cancellable status' => ['orderDateModifier' => 'now', 'status' => null, 'expected' => true];
        yield 'false after the period expired' => ['orderDateModifier' => '-30 days', 'status' => null, 'expected' => false];
        yield 'false for a non-cancellable status' => ['orderDateModifier' => 'now', 'status' => OrderStatus::SHIPPED, 'expected' => false];
    }

    #[Test]
    public function withdrawCancelsTheOrderAndNotifiesCustomerAndMerchant(): void
    {
        $subject = $this->get(WithdrawalService::class);
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

        $subject->withdraw($order, 'shopper@example.com', 'Changed my mind');

        $this->assertSame(OrderStatus::CANCELLED, $order->getStatus());
        $this->assertCount(1, $order->getStatusLog());
        $this->assertSame('Changed my mind', $order->getStatusLog()[0]['note']);
        $recipients = array_map(static fn($email): string => $email->getTo()[0]->getAddress(), TestMailer::getSentEmails());
        $this->assertContains('merchant@example.com', $recipients);
        $this->assertContains('shopper@example.com', $recipients);
    }

    /**
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    #[Test]
    #[DataProvider('withdrawThrowsProvider')]
    public function withdrawThrowsInVariousScenarios(string $orderDateModifier, ?OrderStatus $status, string $email, string $expectedExceptionClass, int $expectedExceptionCode): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder();
        $order->setOrderDate((new \DateTime())->modify($orderDateModifier));
        if ($status !== null) {
            $order->setStatus($status);
        }

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionCode($expectedExceptionCode);

        $subject->withdraw($order, $email, '');
    }

    public static function withdrawThrowsProvider(): \Generator
    {
        yield 'the email does not match' => [
            'orderDateModifier' => 'now', 'status' => null, 'email' => 'someone-else@example.com',
            'expectedExceptionClass' => WithdrawalEmailMismatchException::class, 'expectedExceptionCode' => 1752100001,
        ];
        yield 'the period has expired' => [
            'orderDateModifier' => '-30 days', 'status' => null, 'email' => 'shopper@example.com',
            'expectedExceptionClass' => WithdrawalPeriodExpiredException::class, 'expectedExceptionCode' => 1752100002,
        ];
        yield 'the order is no longer cancellable' => [
            'orderDateModifier' => 'now', 'status' => OrderStatus::SHIPPED, 'email' => 'shopper@example.com',
            'expectedExceptionClass' => OrderNotWithdrawableException::class, 'expectedExceptionCode' => 1752100003,
        ];
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
