<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalStatement;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalItemAvailability;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalRequestException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalNameMissingException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalNoItemSelectedException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class WithdrawalServiceTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_items_and_addresses.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/orders_for_withdrawal.csv');
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
    public function resolveOrderForCustomerReturnsAnOrderOfTheLoggedInCustomer(): void
    {
        $subject = $this->get(WithdrawalService::class);

        $resolved = $subject->resolveOrderForCustomer(31, 5);

        $this->assertSame(31, $resolved->getUid());
    }

    #[Test]
    #[DataProvider('foreignCustomerProvider')]
    public function resolveOrderForCustomerRefusesAnotherCustomersOrder(int $orderUid, int $frontendUserUid): void
    {
        $subject = $this->get(WithdrawalService::class);
        $this->expectException(InvalidWithdrawalRequestException::class);
        $this->expectExceptionCode(1791729349);

        $subject->resolveOrderForCustomer($orderUid, $frontendUserUid);
    }

    public static function foreignCustomerProvider(): \Generator
    {
        yield 'an order of another customer' => ['orderUid' => 31, 'frontendUserUid' => 6];
        yield 'a guest order is never matched by a logged-in customer' => ['orderUid' => 1, 'frontendUserUid' => 5];
        yield 'nobody logged in' => ['orderUid' => 31, 'frontendUserUid' => 0];
    }

    #[Test]
    #[DataProvider('isStillWithdrawableProvider')]
    public function isStillWithdrawableFollowsTheDeliveryOfTheGoods(OrderStatus $status, string $deliveredModifier, bool $expected): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(32);
        $order->setOrderDate(new \DateTime('-40 days'));
        $order->setWithdrawalInformationProvidedAt(new \DateTime('-40 days'));
        $order->setStatus($status);
        if ($deliveredModifier !== '') {
            $order->setDeliveredAt(new \DateTime($deliveredModifier));
        }

        $result = $subject->isStillWithdrawable($order, new WithdrawalSettings(timezone: 'UTC'), new \DateTimeImmutable());

        $this->assertSame($expected, $result);
    }

    public static function isStillWithdrawableProvider(): \Generator
    {
        yield 'an unshipped order stays withdrawable however old it is' => ['status' => OrderStatus::CONFIRMED, 'deliveredModifier' => '', 'expected' => true];
        yield 'a shipped order stays withdrawable while the period runs' => ['status' => OrderStatus::SHIPPED, 'deliveredModifier' => '-3 days', 'expected' => true];
        yield 'a delivered order is withdrawable on the last day' => ['status' => OrderStatus::COMPLETED, 'deliveredModifier' => '-14 days', 'expected' => true];
        yield 'a delivered order is not withdrawable after the period' => ['status' => OrderStatus::COMPLETED, 'deliveredModifier' => '-30 days', 'expected' => false];
        yield 'a cancelled order is not withdrawable' => ['status' => OrderStatus::CANCELLED, 'deliveredModifier' => '', 'expected' => false];
    }

    #[Test]
    public function withdrawCancelsAnUnshippedOrderAndAcknowledgesToTheCustomerAndNotifiesTheMerchant(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(1);
        $order->setOrderDate(new \DateTime());
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', 'Changed my mind', null, new \DateTimeImmutable());

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));

        $this->assertSame(OrderStatus::CANCELLED, $order->getStatus());
        $this->assertSame('Changed my mind', $order->getStatusLog()[0]['note']);
        $recipients = array_map(static fn($email): string => $email->getTo()[0]->getAddress(), TestMailer::getSentEmails());
        $this->assertContains('merchant@example.com', $recipients);
        $this->assertContains('shopper@example.com', $recipients);
    }

    #[Test]
    public function aWithdrawalStandsEvenWhenTheMailServerIsUnreachable(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(1);
        $order->setOrderDate(new \DateTime());
        TestMailer::failAllSends();

        $subject->withdraw($order, new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', null, new \DateTimeImmutable()), new WithdrawalSettings(timezone: 'UTC'));

        $this->assertSame(OrderStatus::CANCELLED, $order->getStatus());
        $this->assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function withdrawRecordsTheStatementOnTheOrderAndItsItems(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setOrderDate(new \DateTime());
        $submittedAt = new \DateTimeImmutable('2026-10-01 10:15:30', new \DateTimeZone('UTC'));
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', 'Too small', [31], $submittedAt);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));

        $this->assertSame($submittedAt->getTimestamp(), $order->getWithdrawnAt()?->getTimestamp());
        $log = $order->getWithdrawalLog();
        $this->assertCount(1, $log);
        $this->assertSame('Jane Shopper', $log[0]['name']);
        $this->assertSame('Too small', $log[0]['reason']);
        $this->assertSame('Red Shoes', $log[0]['items'][0]['title']);
        $items = $order->getItems()->toArray();
        $this->assertNotNull($items[0]->getWithdrawnAt());
        $this->assertNull($items[1]->getWithdrawnAt());
    }

    #[Test]
    public function aPartialWithdrawalFromAnUnshippedOrderPutsOnlyThoseItemsBackIntoStock(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setOrderDate(new \DateTime());
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', [31], new \DateTimeImmutable());

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));

        $this->assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        $this->assertSame(5, $this->stock(31));
        $this->assertSame(1, $this->stock(32));
    }

    #[Test]
    public function withdrawingFromEveryWithdrawableItemOfAnUnshippedOrderKeepsTheExemptItemsOrderAlive(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setOrderDate(new \DateTime());
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', null, new \DateTimeImmutable());

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));

        // The personalized ring is exempt and will still be shipped, so the order is not cancelled.
        $this->assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        $this->assertSame(5, $this->stock(31));
        $this->assertSame(2, $this->stock(32));
        $this->assertSame(0, $this->stock(33));
    }

    #[Test]
    public function withdrawingFromAShippedOrderWaitsForTheReturnInsteadOfCancelling(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(32);
        $order->setOrderDate(new \DateTime('-5 days'));
        $order->setDeliveredAt(new \DateTime('-2 days'));
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', 'Wrong colour', null, new \DateTimeImmutable());

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));

        $this->assertSame(OrderStatus::WITHDRAWN, $order->getStatus());
        $this->assertSame('Wrong colour', $order->getStatusLog()[0]['note']);
        $this->assertSame(3, $this->stock(31), 'the goods are not back in the warehouse yet');
        $this->assertSame(1, $this->stock(32));
        $recipients = array_map(static fn($email): string => $email->getTo()[0]->getAddress(), TestMailer::getSentEmails());
        $this->assertContains('shopper@example.com', $recipients);
        $this->assertContains('merchant@example.com', $recipients);
    }

    #[Test]
    public function aWithdrawalIsTimelyWhenItWasSubmittedBeforeThePeriodExpiredEvenIfProcessedLater(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(32);
        $order->setOrderDate(new \DateTime('-40 days'));
        $order->setWithdrawalInformationProvidedAt(new \DateTime('-40 days'));
        $order->setDeliveredAt(new \DateTime('-20 days'));
        $submittedAt = new \DateTimeImmutable('-10 days');
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', null, $submittedAt);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));

        $this->assertSame(OrderStatus::WITHDRAWN, $order->getStatus());
    }

    #[Test]
    public function aWithdrawalSubmittedAfterThePeriodExpiredIsRefused(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(32);
        $order->setOrderDate(new \DateTime('-40 days'));
        $order->setWithdrawalInformationProvidedAt(new \DateTime('-40 days'));
        $order->setDeliveredAt(new \DateTime('-30 days'));
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', null, new \DateTimeImmutable());
        $this->expectException(WithdrawalPeriodExpiredException::class);
        $this->expectExceptionCode(1752100002);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));
    }

    #[Test]
    public function aSecondPartialWithdrawalAddsToTheFirstOne(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(32);
        $order->setDeliveredAt(new \DateTime('-2 days'));
        $settings = new WithdrawalSettings(timezone: 'UTC');

        $subject->withdraw($order, new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', [34], new \DateTimeImmutable()), $settings);
        $subject->withdraw($order, new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', [35], new \DateTimeImmutable()), $settings);

        $this->assertSame(OrderStatus::WITHDRAWN, $order->getStatus());
        $this->assertCount(2, $order->getWithdrawalLog());
    }

    #[Test]
    public function itemStatesShowExemptAndWithdrawnItemsAsNotSelectable(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setOrderDate(new \DateTime());
        $items = $order->getItems()->toArray();
        $items[0]->setWithdrawnAt(new \DateTime());

        $states = $subject->itemStates($order, new WithdrawalSettings(timezone: 'UTC'), new \DateTimeImmutable());

        $this->assertSame(WithdrawalItemAvailability::WITHDRAWN, $states[0]->availability);
        $this->assertSame(WithdrawalItemAvailability::WITHDRAWABLE, $states[1]->availability);
        $this->assertSame(WithdrawalItemAvailability::EXEMPT, $states[2]->availability);
    }

    #[Test]
    public function aSealedItemStaysWithdrawableBecauseTheShopCannotKnowWhetherItWasUnsealed(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(32);
        $order->setDeliveredAt(new \DateTime('-2 days'));

        $states = $subject->itemStates($order, new WithdrawalSettings(timezone: 'UTC'), new \DateTimeImmutable());

        $this->assertSame(WithdrawalItemAvailability::WITHDRAWABLE, $states[1]->availability);
    }

    #[Test]
    public function withdrawRefusesAnExemptItem(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setOrderDate(new \DateTime());
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', [33], new \DateTimeImmutable());
        $this->expectException(OrderNotWithdrawableException::class);
        $this->expectExceptionCode(1791729351);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));
    }

    #[Test]
    public function withdrawRefusesAStatementWithoutAName(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setOrderDate(new \DateTime());
        $statement = new WithdrawalStatement('  ', 'shopper@example.com', '', null, new \DateTimeImmutable());
        $this->expectException(WithdrawalNameMissingException::class);
        $this->expectExceptionCode(1791729679);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));
    }

    #[Test]
    public function withdrawRefusesAnExplicitlyEmptyItemSelection(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setOrderDate(new \DateTime());
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', [], new \DateTimeImmutable());
        $this->expectException(WithdrawalNoItemSelectedException::class);
        $this->expectExceptionCode(1791729681);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));
    }

    #[Test]
    public function withdrawRefusesAMismatchingEmail(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $statement = new WithdrawalStatement('Jane Shopper', 'someone-else@example.com', '', null, new \DateTimeImmutable());
        $this->expectException(WithdrawalEmailMismatchException::class);
        $this->expectExceptionCode(1752100001);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));
    }

    #[Test]
    public function withdrawRefusesACancelledOrder(): void
    {
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(31);
        $order->setStatus(OrderStatus::CANCELLED);
        $statement = new WithdrawalStatement('Jane Shopper', 'shopper@example.com', '', null, new \DateTimeImmutable());
        $this->expectException(OrderNotWithdrawableException::class);
        $this->expectExceptionCode(1752100003);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));
    }

    #[Test]
    public function withdrawThrowsForAnOrderOfOnlyExemptItems(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_excluded_items.csv');
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder(11);
        $order->setOrderDate(new \DateTime());
        $statement = new WithdrawalStatement('Per Sonalized', 'personalized@example.com', '', null, new \DateTimeImmutable());
        $this->expectException(OrderNotWithdrawableException::class);
        $this->expectExceptionCode(1784937602);

        $subject->withdraw($order, $statement, new WithdrawalSettings(timezone: 'UTC'));
    }

    #[Test]
    #[DataProvider('exclusionWithdrawabilityProvider')]
    public function isStillWithdrawableHonoursArt16Exclusions(int $orderUid, bool $expected): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_with_excluded_items.csv');
        $subject = $this->get(WithdrawalService::class);
        $order = $this->fetchOrder($orderUid);
        $order->setOrderDate(new \DateTime());

        $this->assertSame($expected, $subject->isStillWithdrawable($order, new WithdrawalSettings(timezone: 'UTC'), new \DateTimeImmutable()));
    }

    public static function exclusionWithdrawabilityProvider(): \Generator
    {
        yield 'false for an order of only personalized items' => ['orderUid' => 11, 'expected' => false];
        yield 'false for digital content after the waiver was consented' => ['orderUid' => 12, 'expected' => false];
        yield 'true for digital content without the waiver' => ['orderUid' => 13, 'expected' => true];
        yield 'true for a mixed order - one item retains the right' => ['orderUid' => 14, 'expected' => true];
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

    private function stock(int $productUid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('in_stock')
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
