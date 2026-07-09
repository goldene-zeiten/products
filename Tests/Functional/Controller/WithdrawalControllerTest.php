<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Withdrawal\WithdrawalTokenService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\TestMailer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class WithdrawalControllerTest extends AbstractFrontendTestCase
{
    private OrderRepository $orderRepository;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_with_items_and_addresses.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/withdrawal_content.csv');
        $this->orderRepository = $this->get(OrderRepository::class);

        $order = $this->orderRepository->findByUidIgnoringStoragePage(1);
        self::assertInstanceOf(Order::class, $order);
        $order->setOrderDate(new \DateTime());
        $this->orderRepository->update($order);
        $this->get(PersistenceManagerInterface::class)->persistAll();
        $this->order = $order;
    }

    #[Test]
    public function formActionRendersTheFormForAValidToken(): void
    {
        $token = $this->get(WithdrawalTokenService::class)->generateToken($this->order);

        $response = $this->executeFrontendSubRequest($this->requestFor('form', [
            'order' => $this->orderUid(),
            'hash' => $token,
        ]));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('tx_products_withdrawal[email]', (string)$response->getBody());
    }

    #[Test]
    public function formActionShowsTheInvalidLinkMessageForATamperedToken(): void
    {
        $response = $this->executeFrontendSubRequest($this->requestFor('form', [
            'order' => $this->orderUid(),
            'hash' => 'not-a-valid-token',
        ]));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('tx_products_withdrawal[email]', (string)$response->getBody());
    }

    #[Test]
    public function confirmActionCancelsTheOrderForValidInput(): void
    {
        $token = $this->get(WithdrawalTokenService::class)->generateToken($this->order);

        $response = $this->executeFrontendSubRequest($this->requestFor('confirm', [
            'order' => $this->orderUid(),
            'hash' => $token,
            'email' => 'shopper@example.com',
            'reason' => 'Changed my mind',
        ]));

        self::assertSame(200, $response->getStatusCode());
        $cancelled = $this->orderRepository->findByUidIgnoringStoragePage(1);
        self::assertInstanceOf(Order::class, $cancelled);
        self::assertSame(OrderStatus::CANCELLED, $cancelled->getStatus());
    }

    private function orderUid(): int
    {
        $uid = $this->order->getUid();
        self::assertNotNull($uid);
        return $uid;
    }

    /**
     * @param array<string, int|string> $arguments
     */
    private function requestFor(string $action, array $arguments): InternalRequest
    {
        $queryParameters = ['tx_products_withdrawal[action]' => $action];
        foreach ($arguments as $name => $value) {
            $queryParameters[sprintf('tx_products_withdrawal[%s]', $name)] = $value;
        }

        // CacheHashCalculator::splitQueryStringToArray() rawurldecode()s each value (it does not
        // treat "+" as a space, unlike urldecode()), so the query string fed into it must be
        // percent-encoded via RFC3986 (%20), not http_build_query()'s RFC1738 default ("+") -
        // otherwise a value containing a space (e.g. the "reason" argument) computes a cHash that
        // never matches what the real dispatched request recomputes, and TYPO3 answers 404.
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&' . http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986)
        );
        $queryParameters['cHash'] = $cHash;

        return (new InternalRequest('http://localhost/shop'))->withQueryParameters($queryParameters);
    }
}
