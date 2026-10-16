<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Before placing the order the customer sees everything the order will carry, and the pages the terms
 * checkbox refers to.
 */
final class CheckoutReviewDetailsTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CheckoutStepValidationTest/checkout_basket.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'pids' => [
                            'termsPage' => 2,
                            'privacyPage' => 2,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    #[DataProvider('reviewContentProvider')]
    public function theReviewShowsTheCustomerDetailsAndTheLegalPages(string $expectedContent): void
    {
        $this->startCheckoutSession();

        $body = $this->renderReview();

        $this->assertStringContainsString($expectedContent, $body);
    }

    public static function reviewContentProvider(): \Generator
    {
        yield 'company' => [
            'expectedContent' => 'Goldschmiede Muster',
        ];
        yield 'telephone' => [
            'expectedContent' => '+49 30 123456',
        ];
        yield 'translated salutation' => [
            'expectedContent' => 'Ms Jane Doe',
        ];
        yield 'comment' => [
            'expectedContent' => 'Please call before delivery.',
        ];
        yield 'desired delivery date' => [
            'expectedContent' => '2030-12-23',
        ];
        yield 'terms page link' => [
            'expectedContent' => 'Terms and conditions</a>',
        ];
        yield 'privacy page link' => [
            'expectedContent' => 'Privacy policy</a>',
        ];
    }

    #[Test]
    public function aLegalPageThatIsNotConfiguredIsNotLinked(): void
    {
        $this->startCheckoutSession();

        $this->assertStringNotContainsString('Withdrawal instructions</a>', $this->renderReview());
    }

    private function renderReview(): string
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_checkout[action]=review');
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))
                ->withQueryParameters([
                    'tx_productscore_checkout[action]' => 'review',
                    'cHash' => $cHash,
                ])
                ->withCookieParams(['fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt()])
        );
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }

    private function startCheckoutSession(): void
    {
        $address = new Address(
            email: 'buyer@example.com',
            salutation: 'ms',
            firstName: 'Jane',
            lastName: 'Doe',
            company: 'Goldschmiede Muster',
            street: 'Main Street 1',
            zip: '10115',
            city: 'Berlin',
            country: 'DE',
            telephone: '+49 30 123456',
        );
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize([
                'tx_products_basket' => '{"items":[{"productUid":1,"articleUid":null,"quantity":1}]}',
                'tx_products_checkout_address' => serialize($address),
                'tx_products_checkout_payment' => 'invoice',
                'tx_products_checkout_customer_note' => 'Please call before delivery.',
                'tx_products_checkout_desired_delivery_date' => '2030-12-23',
            ]),
        ]);
    }
}
