<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalUrlFactory;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The withdrawal link of an order mail has to work as it is printed: one valid cHash, and in the plain-text
 * mail no HTML entities.
 */
final class WithdrawalLinkTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/OrderMailContentTest/complete_order.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => ['products' => ['pids' => ['withdrawalPage' => 2]]],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    public function theLinkCarriesOneCacheHash(): void
    {
        $url = (string)$this->get(WithdrawalUrlFactory::class)->createFor($this->fetchOrder());

        $this->assertSame(1, substr_count($url, 'cHash='), $url);
    }

    #[Test]
    public function thePlainTextMailPrintsTheLinkUnescaped(): void
    {
        $order = $this->fetchOrder();
        $url = (string)$this->get(WithdrawalUrlFactory::class)->createFor($order);

        $this->get(OrderMailService::class)->sendOrderConfirmation($order);

        $text = (string)TestMailer::getSentEmails()[0]->getTextBody();
        $this->assertStringContainsString($url, $text);
        $this->assertStringNotContainsString('&amp;', $text);
    }

    private function fetchOrder(): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }
}
