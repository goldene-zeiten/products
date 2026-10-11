<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Security\Nonce;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A checkout step that changes the customer's checkout - the address, the shipping option, the payment
 * method, placing the order - is only accepted with the request token the checkout's own forms carry, so a
 * cross-site request cannot drive it.
 */
final class CheckoutRequestTokenTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'tttttttttttttttttttttttttttttttt';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-payment-fixture',
    ];

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
    }

    /**
     * @param array<string, string> $session
     * @param array<string, string> $arguments
     */
    #[Test]
    #[DataProvider('stateChangingStepProvider')]
    public function aStepWithoutTheCheckoutTokenIsSentBackUnchanged(array $session, array $arguments, string $backTo, string $sessionKey, string $sessionValue): void
    {
        $this->startCheckoutSession($session);
        $response = $this->executeFrontendSubRequest($this->postRequest($arguments, null));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=' . $backTo, urldecode($response->getHeaderLine('location')));
        $this->assertSame($sessionValue, $this->sessionValue($sessionKey));
        $this->assertNoOrderWasPlaced();
    }

    /**
     * @param array<string, string> $session
     * @param array<string, string> $arguments
     */
    #[Test]
    #[DataProvider('stateChangingStepProvider')]
    public function aStepWithATokenOfAnotherScopeIsSentBack(array $session, array $arguments, string $backTo, string $sessionKey, string $sessionValue): void
    {
        $this->startCheckoutSession($session);
        $response = $this->executeFrontendSubRequest($this->postRequest($arguments, 'products/basket'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=' . $backTo, urldecode($response->getHeaderLine('location')));
        $this->assertSame($sessionValue, $this->sessionValue($sessionKey));
        $this->assertNoOrderWasPlaced();
    }

    public static function stateChangingStepProvider(): \Generator
    {
        yield 'address' => [
            'session' => [],
            'arguments' => ['action' => 'submitAddress'],
            'backTo' => 'address',
            'sessionKey' => 'tx_products_checkout_address',
            'sessionValue' => serialize(new Address(
                email: 'buyer@example.com',
                firstName: 'Jane',
                lastName: 'Doe',
                street: 'Main Street 1',
                zip: '10115',
                city: 'Berlin',
                country: 'DE'
            )),
        ];
        yield 'shipping option' => [
            'session' => [],
            'arguments' => ['action' => 'submitShippingMethod', 'shippingOption' => 'tablerate:1'],
            'backTo' => 'shippingMethod',
            'sessionKey' => 'tx_products_checkout_shipping_option',
            'sessionValue' => '',
        ];
        yield 'payment method' => [
            'session' => [],
            'arguments' => ['action' => 'submitPayment', 'paymentMethod' => 'invoice'],
            'backTo' => 'payment',
            'sessionKey' => 'tx_products_checkout_payment',
            'sessionValue' => '',
        ];
        yield 'placing the order' => [
            'session' => [
                'tx_products_checkout_payment' => 'invoice',
                'tx_products_checkout_shipping_option' => 'tablerate:1',
            ],
            'arguments' => ['action' => 'finalize', 'termsAccepted' => '1'],
            'backTo' => 'review',
            'sessionKey' => 'tx_products_checkout_payment',
            'sessionValue' => 'invoice',
        ];
    }

    #[Test]
    public function theOrderIsOnlyPlacedByAPostRequest(): void
    {
        $this->startCheckoutSession([
            'tx_products_checkout_payment' => 'invoice',
            'tx_products_checkout_shipping_option' => 'tablerate:1',
        ]);
        $nonce = Nonce::create();
        $request = $this->checkoutRequest(['action' => 'finalize', 'termsAccepted' => '1'])
            ->withHeader(RequestToken::HEADER_NAME, RequestToken::create('products/checkout')->toHashSignedJwt($nonce))
            ->withCookieParams([
                'fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt(),
                'typo3nonce_' . $nonce->getSigningIdentifier()->name => $nonce->toHashSignedJwt(),
            ]);

        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=review', urldecode($response->getHeaderLine('location')));
        $this->assertNoOrderWasPlaced();
    }

    #[Test]
    public function aStepWithTheCheckoutTokenIsAccepted(): void
    {
        $this->startCheckoutSession([]);

        $this->executeFrontendSubRequest($this->postRequest(['action' => 'submitPayment', 'paymentMethod' => 'invoice'], 'products/checkout'));

        $this->assertSame('invoice', $this->sessionValue('tx_products_checkout_payment'));
    }

    /**
     * @param array<string, string> $arguments Plugin arguments; "action" goes into the query string
     */
    private function postRequest(array $arguments, ?string $tokenScope): InternalRequest
    {
        $action = $arguments['action'];
        unset($arguments['action']);
        $cookies = ['fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt()];
        $parsedBody = ['tx_productscore_checkout' => $arguments];
        if ($tokenScope !== null) {
            $nonce = Nonce::create();
            $cookies['typo3nonce_' . $nonce->getSigningIdentifier()->name] = $nonce->toHashSignedJwt();
            $parsedBody[RequestToken::PARAM_NAME] = RequestToken::create($tokenScope)->toHashSignedJwt($nonce);
        }

        return $this->checkoutRequest(['action' => $action])
            ->withMethod('POST')
            ->withParsedBody($parsedBody)
            ->withBody($this->get(StreamFactoryInterface::class)->createStream(http_build_query($parsedBody)))
            ->withCookieParams($cookies);
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

        return (new InternalRequest('http://localhost/shop'))->withQueryParameters($queryParameters);
    }

    /**
     * @param array<string, string> $checkoutData
     */
    private function startCheckoutSession(array $checkoutData): void
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
            ] + $checkoutData),
        ]);
    }

    private function sessionValue(string $key): string
    {
        $session = $this->get(SessionManager::class)->getSessionBackend('FE')->get(self::SESSION_ID);
        $sessionData = unserialize((string)$session['ses_data'], ['allowed_classes' => false]);

        return is_array($sessionData) ? (string)($sessionData[$key] ?? '') : '';
    }

    private function assertNoOrderWasPlaced(): void
    {
        $this->assertSame(0, $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order')->count('*', 'tx_products_domain_model_order', []));
    }
}
