<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class WithdrawalControllerTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_with_items_and_addresses.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/WithdrawalControllerTest/withdrawal_content.csv');
    }

    #[Test]
    #[DataProvider('formActionTokenProvider')]
    public function formActionRendersOrHidesTheFormDependingOnTokenValidity(bool $useValidToken, bool $expectFormVisible): void
    {
        $order = $this->fetchWithinWithdrawalPeriodOrder();
        $hash = $useValidToken
            ? $this->get(WithdrawalTokenService::class)->generateToken($order)
            : 'not-a-valid-token';

        $response = $this->executeFrontendSubRequest($this->requestFor('form', [
            'order' => $this->orderUid($order),
            'hash' => $hash,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        if ($expectFormVisible) {
            $this->assertStringContainsString('tx_productscore_withdrawal[email]', (string)$response->getBody());
        } else {
            $this->assertStringNotContainsString('tx_productscore_withdrawal[email]', (string)$response->getBody());
        }
    }

    public static function formActionTokenProvider(): \Generator
    {
        yield 'valid token renders the form' => [
            'useValidToken' => true,
            'expectFormVisible' => true,
        ];

        yield 'tampered token shows the invalid link message' => [
            'useValidToken' => false,
            'expectFormVisible' => false,
        ];
    }

    #[Test]
    public function confirmActionCancelsTheOrderForValidInput(): void
    {
        $order = $this->fetchWithinWithdrawalPeriodOrder();
        $token = $this->get(WithdrawalTokenService::class)->generateToken($order);

        $response = $this->executeFrontendSubRequest($this->requestFor('confirm', [
            'order' => $this->orderUid($order),
            'hash' => $token,
            'email' => 'shopper@example.com',
            'reason' => 'Changed my mind',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/withdrawal_confirm_cancels_order.csv');
    }

    /**
     * Pins order_date to now so the withdrawal window remains valid regardless of period settings.
     */
    private function fetchWithinWithdrawalPeriodOrder(): Order
    {
        $orderRepository = $this->get(OrderRepository::class);
        $order = $orderRepository->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        $order->setOrderDate(new \DateTime());
        $orderRepository->update($order);
        $this->get(PersistenceManagerInterface::class)->persistAll();
        return $order;
    }

    private function orderUid(Order $order): int
    {
        $uid = $order->getUid();
        $this->assertNotNull($uid);
        return $uid;
    }

    /**
     * @param array<string, int|string> $arguments
     */
    private function requestFor(string $action, array $arguments): InternalRequest
    {
        $queryParameters = ['tx_productscore_withdrawal[action]' => $action];
        foreach ($arguments as $name => $value) {
            $queryParameters[sprintf('tx_productscore_withdrawal[%s]', $name)] = $value;
        }

        // RFC3986 encoding required; RFC1738 "+" breaks cHash for values with spaces.
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&' . http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986)
        );
        $queryParameters['cHash'] = $cHash;

        return (new InternalRequest('http://localhost/shop'))->withQueryParameters($queryParameters);
    }
}
