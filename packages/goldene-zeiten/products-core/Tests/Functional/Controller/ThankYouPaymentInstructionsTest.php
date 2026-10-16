<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A customer who still has to pay - e.g. transfer the amount for prepayment - learns how right after
 * ordering, not only from the confirmation mail.
 */
final class ThankYouPaymentInstructionsTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-payment-fixture',
    ];

    #[Test]
    public function theThankYouPageShowsThePaymentInstructions(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/order_with_instructions_payment.csv');
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertNotNull($order);
        $hash = $this->get(OrderTokenService::class)->generateToken($order);
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_productscore_checkout[action]=thankYou&tx_productscore_checkout[order]=1&tx_productscore_checkout[hash]=' . $hash
        );

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/shop'))->withQueryParameters([
            'tx_productscore_checkout[action]' => 'thankYou',
            'tx_productscore_checkout[order]' => 1,
            'tx_productscore_checkout[hash]' => $hash,
            'cHash' => $cHash,
        ]));

        $this->assertStringContainsString('Transfer the total to IBAN DE00 1234 5678, reference ORD-7.', (string)$response->getBody());
    }

    #[Test]
    public function aMethodWithoutInstructionsShowsNone(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/order_with_instructions_payment.csv');
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(2);
        $this->assertNotNull($order);
        $hash = $this->get(OrderTokenService::class)->generateToken($order);
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_productscore_checkout[action]=thankYou&tx_productscore_checkout[order]=2&tx_productscore_checkout[hash]=' . $hash
        );

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/shop'))->withQueryParameters([
            'tx_productscore_checkout[action]' => 'thankYou',
            'tx_productscore_checkout[order]' => 2,
            'tx_productscore_checkout[hash]' => $hash,
            'cHash' => $cHash,
        ]));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('ORD-8', $body);
        $this->assertStringNotContainsString('payment-instructions', $body);
    }
}
