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
    public function formActionPrefillsOrFallsBackToThePlainFormDependingOnTokenValidity(bool $useValidToken): void
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
        $body = (string)$response->getBody();
        if ($useValidToken) {
            $this->assertStringContainsString('value="ORD-1"', $body);
            $this->assertStringContainsString('value="shopper@example.com"', $body);
            $this->assertStringNotContainsString('This cancellation link is invalid or has expired.', $body);
        } else {
            $this->assertStringContainsString('This cancellation link is invalid or has expired.', $body);
            $this->assertStringContainsString('tx_productscore_withdrawal[orderNumber]', $body);
            $this->assertStringNotContainsString('ORD-1', $body);
        }
    }

    public static function formActionTokenProvider(): \Generator
    {
        yield 'valid token prefills the form' => [
            'useValidToken' => true,
        ];

        yield 'tampered token shows the invalid link message and the plain form' => [
            'useValidToken' => false,
        ];
    }

    #[Test]
    public function formActionWithoutATokenRendersThePlainForm(): void
    {
        $response = $this->executeFrontendSubRequest($this->requestFor('form', []));

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('tx_productscore_withdrawal[orderNumber]', $body);
        $this->assertStringContainsString('tx_productscore_withdrawal[email]', $body);
        $this->assertStringNotContainsString('This cancellation link is invalid or has expired.', $body);
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

    #[Test]
    public function confirmActionCancelsTheOrderForAPlainOrderNumberAndEmail(): void
    {
        $this->fetchWithinWithdrawalPeriodOrder();

        $response = $this->executeFrontendSubRequest($this->requestFor('confirm', [
            'orderNumber' => 'ORD-1',
            'email' => 'shopper@example.com',
            'reason' => 'Found a better price',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/withdrawal_confirm_cancels_order.csv');
    }

    /**
     * The redirect back to the form is emitted via header() by Extbase's USER_INT handling and is
     * therefore invisible to the sub-request harness - what this test pins is the contract that
     * matters: a non-matching pair never renders a confirmation and never touches the order.
     */
    #[Test]
    public function confirmActionLeavesTheOrderUntouchedForANonMatchingPlainPair(): void
    {
        $this->fetchWithinWithdrawalPeriodOrder();

        $response = $this->executeFrontendSubRequest($this->requestFor('confirm', [
            'orderNumber' => 'ORD-1',
            'email' => 'someone-else@example.com',
        ]));

        $this->assertStringNotContainsString('Order cancelled', (string)$response->getBody());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/withdrawal_confirm_leaves_order_confirmed.csv');
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
