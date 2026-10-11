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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
            'name' => 'Jane Shopper',
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
            'name' => 'Jane Shopper',
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
            'name' => 'Jane Shopper',
        ]));

        $this->assertStringNotContainsString('Withdrawal received', (string)$response->getBody());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/withdrawal_confirm_leaves_order_confirmed.csv');
    }

    #[Test]
    public function theWithdrawalPageShowsTheModelInstructionsAndTheModelForm(): void
    {
        $response = $this->executeFrontendSubRequest($this->requestFor('form', []));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('Model instructions on withdrawal', $body);
        $this->assertStringContainsString('You have the right to withdraw from this contract within 14 days without giving any reason.', $body);
        $this->assertStringContainsString('You can also exercise your right of withdrawal online at', $body);
        $this->assertStringContainsString('Model withdrawal form', $body);
        $this->assertStringContainsString('Ordered on (*)/received on (*),', $body);
    }

    #[Test]
    public function theInstructionsContentElementRendersTheModelTextsForATermsPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/WithdrawalControllerTest/withdrawal_instructions_content.csv');

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'));

        $body = (string)$response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Effects of withdrawal', $body);
        $this->assertStringContainsString('Exceptions from the right of withdrawal', $body);
        $this->assertStringContainsString('Model withdrawal form', $body);
    }

    #[Test]
    public function selectActionListsTheItemsOfTheIdentifiedOrderAndOffersOnlyTheConfirmButton(): void
    {
        $this->fetchWithinWithdrawalPeriodOrder();

        $response = $this->executeFrontendSubRequest($this->requestFor('select', [
            'orderNumber' => 'ORD-1',
            'email' => 'shopper@example.com',
            'name' => 'Jane Shopper',
        ]));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('Red Shoes', $body);
        $this->assertStringContainsString('tx_productscore_withdrawal[items][]', $body);
        $this->assertStringContainsString('value="Confirm withdrawal"', $body);
        $this->assertStringContainsString('value="Jane Shopper"', $body);
    }

    #[Test]
    public function selectActionRevealsNothingAboutAnOrderForANonMatchingPair(): void
    {
        $this->fetchWithinWithdrawalPeriodOrder();

        $response = $this->executeFrontendSubRequest($this->requestFor('select', [
            'orderNumber' => 'ORD-1',
            'email' => 'someone-else@example.com',
        ]));

        $this->assertStringNotContainsString('Red Shoes', (string)$response->getBody());
    }

    #[Test]
    public function confirmActionWithdrawsOnlyTheSelectedItems(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/orders_for_withdrawal.csv');
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(31);
        $this->assertInstanceOf(Order::class, $order);
        $order->setOrderDate(new \DateTime());
        $this->get(OrderRepository::class)->update($order);
        $this->get(PersistenceManagerInterface::class)->persistAll();

        $response = $this->executeFrontendSubRequest($this->requestFor('confirm', [
            'orderNumber' => 'ORD-W-OPEN',
            'email' => 'shopper@example.com',
            'name' => 'Jane Shopper',
            'itemSelection' => 1,
            'items' => [32],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Withdrawal received', (string)$response->getBody());
        $this->assertSame(0, $this->withdrawnAtOf(31));
        $this->assertGreaterThan(0, $this->withdrawnAtOf(32));
    }

    #[Test]
    public function confirmActionRefusesAnEmptyItemSelection(): void
    {
        $this->fetchWithinWithdrawalPeriodOrder();

        $response = $this->executeFrontendSubRequest($this->requestFor('confirm', [
            'orderNumber' => 'ORD-1',
            'email' => 'shopper@example.com',
            'name' => 'Jane Shopper',
            'itemSelection' => 1,
        ]));

        $this->assertStringNotContainsString('Withdrawal received', (string)$response->getBody());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/withdrawal_confirm_leaves_order_confirmed.csv');
    }

    #[Test]
    public function confirmActionRefusesAWithdrawalWithoutAName(): void
    {
        $this->fetchWithinWithdrawalPeriodOrder();

        $response = $this->executeFrontendSubRequest($this->requestFor('confirm', [
            'orderNumber' => 'ORD-1',
            'email' => 'shopper@example.com',
        ]));

        $this->assertStringNotContainsString('Withdrawal received', (string)$response->getBody());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/withdrawal_confirm_leaves_order_confirmed.csv');
    }

    private function withdrawnAtOf(int $itemUid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_orderitem');
        return (int)$queryBuilder->select('withdrawn_at')
            ->from('tx_products_domain_model_orderitem')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($itemUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
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
     * @param array<string, int|string|array<int, int>> $arguments
     */
    private function requestFor(string $action, array $arguments): InternalRequest
    {
        $queryParameters = ['tx_productscore_withdrawal[action]' => $action];
        foreach ($arguments as $name => $value) {
            foreach (is_array($value) ? $value : [null => $value] as $index => $entry) {
                $queryParameters[sprintf('tx_productscore_withdrawal[%s]', $name) . (is_array($value) ? sprintf('[%d]', $index) : '')] = $entry;
            }
        }

        // RFC3986 encoding required; RFC1738 "+" breaks cHash for values with spaces.
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&' . http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986)
        );
        $queryParameters['cHash'] = $cHash;

        return (new InternalRequest('http://localhost/shop'))->withQueryParameters($queryParameters);
    }
}
