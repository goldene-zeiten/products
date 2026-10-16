<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Security\Nonce;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The checkout plugin is uncached, so a completed step has to propagate its redirect to the next step
 * as the response of the request, instead of sending a header beside a rendered page.
 */
final class CheckoutStepRedirectTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'ffffffffffffffffffffffffffffffff';

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
                        'shipping' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->startCheckoutSession();
    }

    /**
     * @param array<string, string> $arguments
     */
    #[Test]
    #[DataProvider('completedStepProvider')]
    public function aCompletedStepRedirectsToTheNextStep(array $arguments, string $nextAction): void
    {
        $response = $this->executeFrontendSubRequest($this->checkoutRequest($arguments));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=' . $nextAction, urldecode($response->getHeaderLine('location')));
    }

    public static function completedStepProvider(): \Generator
    {
        yield 'shipping method chosen' => [
            'arguments' => ['action' => 'submitShippingMethod', 'shippingOption' => 'tablerate:1'],
            'nextAction' => 'payment',
        ];
        yield 'payment method chosen' => [
            'arguments' => ['action' => 'submitPayment', 'paymentMethod' => 'invoice'],
            'nextAction' => 'review',
        ];
    }

    private function startCheckoutSession(): void
    {
        $address = new Address(
            email: 'buyer@example.com',
            firstName: 'Jane',
            lastName: 'Doe',
            street: 'Main Street 1',
            zip: '10115',
            city: 'Berlin',
            country: 'DE'
        );
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize([
                'tx_products_basket' => '{"items":[{"productUid":1,"articleUid":null,"quantity":1}]}',
                'tx_products_checkout_address' => serialize($address),
            ]),
        ]);
    }

    /**
     * @param array<string, string> $arguments
     */
    private function checkoutRequest(array $arguments): InternalRequest
    {
        $queryParameters = [];
        $parameterString = '&id=2';
        foreach ($arguments as $name => $value) {
            $queryParameters['tx_productscore_checkout[' . $name . ']'] = $value;
            $parameterString .= '&tx_productscore_checkout[' . $name . ']=' . $value;
        }
        $queryParameters['cHash'] = $this->get(CacheHashCalculator::class)->generateForParameters($parameterString);
        $nonce = Nonce::create();

        return (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters($queryParameters)
            ->withHeader(RequestToken::HEADER_NAME, RequestToken::create('products/checkout')->toHashSignedJwt($nonce))
            ->withCookieParams([
                'fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt(),
                'typo3nonce_' . $nonce->getSigningIdentifier()->name => $nonce->toHashSignedJwt(),
            ]);
    }
}
