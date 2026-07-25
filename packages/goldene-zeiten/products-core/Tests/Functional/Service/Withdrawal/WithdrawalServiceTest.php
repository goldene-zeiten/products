<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalRequestException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
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

        $request = (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->assertSame($expected, $subject->isStillWithdrawable($order, $request));
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
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
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

        $request = (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $subject->withdraw($order, 'shopper@example.com', 'Changed my mind', $request);

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

        $request = (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $subject->withdraw($order, $email, '', $request);
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

    #[Test]
    #[DataProvider('exclusionWithdrawabilityProvider')]
    public function isStillWithdrawableHonoursArt16Exclusions(int $orderUid, bool $expected): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_excluded_items.csv');
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder($orderUid);
        $order->setOrderDate(new \DateTime());

        $request = (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->assertSame($expected, $subject->isStillWithdrawable($order, $request));
    }

    public static function exclusionWithdrawabilityProvider(): \Generator
    {
        yield 'false for an order of only personalized items' => ['orderUid' => 11, 'expected' => false];
        yield 'false for digital content after the waiver was consented' => ['orderUid' => 12, 'expected' => false];
        yield 'true for digital content without the waiver' => ['orderUid' => 13, 'expected' => true];
        yield 'true for a mixed order - one item retains the right' => ['orderUid' => 14, 'expected' => true];
    }

    #[Test]
    public function withdrawThrowsForAFullyExcludedOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_excluded_items.csv');
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(11);
        $order->setOrderDate(new \DateTime());

        $this->expectException(OrderNotWithdrawableException::class);
        $this->expectExceptionCode(1784937602);

        $request = (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $subject->withdraw($order, 'personalized@example.com', '', $request);
    }

    #[Test]
    public function resolveOrderByNumberAndEmailResolvesCaseInsensitively(): void
    {
        $subject = $this->get(WithdrawalService::class);

        $resolved = $subject->resolveOrderByNumberAndEmail('ORD-1', 'SHOPPER@example.com');

        $this->assertSame(1, $resolved->getUid());
    }

    #[Test]
    #[DataProvider('resolveByNumberAndEmailThrowsProvider')]
    public function resolveOrderByNumberAndEmailThrowsForANonMatchingPair(string $orderNumber, string $email): void
    {
        $subject = $this->get(WithdrawalService::class);

        $this->expectException(InvalidWithdrawalRequestException::class);
        $this->expectExceptionCode(1784937601);

        $subject->resolveOrderByNumberAndEmail($orderNumber, $email);
    }

    public static function resolveByNumberAndEmailThrowsProvider(): \Generator
    {
        yield 'the email belongs to a different order' => ['orderNumber' => 'ORD-1', 'email' => 'someone-else@example.com'];
        yield 'the order number does not exist' => ['orderNumber' => 'ORD-UNKNOWN', 'email' => 'shopper@example.com'];
        yield 'an empty order number never matches' => ['orderNumber' => '', 'email' => 'shopper@example.com'];
    }

    private function fetchOrder(int $uid = 1): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage($uid);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
