<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Tests\Functional\EventListener;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Voucher\EventListener\IssueGainedVoucherListener;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The reward voucher is issued inside the order placement's transaction. A database error while issuing
 * it must leave that transaction usable: on PostgreSQL a failed statement otherwise aborts it, and the
 * order is lost on commit.
 */
final class IssueGainedVoucherInTransactionTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-voucher',
    ];

    #[Test]
    public function aFailingVoucherIssueKeepsTheSurroundingTransaction(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_products_domain_model_order');
        $connection->beginTransaction();
        $connection->insert('tx_products_domain_model_order', ['uid' => 1, 'pid' => 0, 'order_number' => 'ORD-1', 'total_gross' => 10000]);
        $order = new Order();
        $order->_setProperty('uid', 1);
        $order->setOrderNumber('ORD-1');
        $order->setTotalGross(Money::fromCents(10000));
        // A reward value the decimal column refuses makes the voucher insert fail in the database.
        $site = new Site('products', 1, [
            'settings' => [
                'products' => [
                    'vouchers' => [
                        'gained' => [
                            'enabled' => true,
                            'rewardType' => 'fixed',
                            'rewardValue' => 'not-a-number',
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(IssueGainedVoucherListener::class)(new AfterOrderPlacedEvent($order, (new ServerRequest('http://localhost/'))->withAttribute('site', $site)));
        $connection->insert('tx_products_domain_model_order', ['uid' => 2, 'pid' => 0, 'order_number' => 'ORD-2']);
        $connection->commit();

        $this->assertCount(2, $this->getAllRecords('tx_products_domain_model_order'));
    }
}
