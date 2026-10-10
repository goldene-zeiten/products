<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Every checkout step stores what the customer submitted, so a step only accepts a choice the shop would
 * actually offer, and sends the customer back to it - with a message - when the choice is missing or stale.
 */
final class CheckoutStepValidationTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'cccccccccccccccccccccccccccccccc';

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
     * @param array<string, string> $arguments
     */
    #[Test]
    #[DataProvider('unofferedShippingOptionProvider')]
    public function submitShippingMethodSendsTheCustomerBackForAnOptionThatIsNotOffered(array $arguments): void
    {
        $this->startCheckoutSession([]);

        $response = $this->executeFrontendSubRequest($this->checkoutRequest(['action' => 'submitShippingMethod'] + $arguments));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=shippingMethod', urldecode($response->getHeaderLine('location')));
        $this->assertSame('', $this->checkoutSessionValue('tx_products_checkout_shipping_option'));
    }

    public static function unofferedShippingOptionProvider(): \Generator
    {
        yield 'no option chosen' => [
            'arguments' => [],
        ];
        yield 'option that no carrier offers' => [
            'arguments' => ['shippingOption' => 'tablerate:999'],
        ];
        yield 'option of a carrier that does not serve the address' => [
            'arguments' => ['shippingOption' => 'fixture-shipping:standard'],
        ];
    }

    #[Test]
    public function submitShippingMethodStoresAnOfferedOption(): void
    {
        $this->startCheckoutSession([]);

        $this->executeFrontendSubRequest($this->checkoutRequest(['action' => 'submitShippingMethod', 'shippingOption' => 'tablerate:1']));

        $this->assertSame('tablerate:1', $this->checkoutSessionValue('tx_products_checkout_shipping_option'));
    }

    #[Test]
    #[DataProvider('unresolvableShippingOptionProvider')]
    public function reviewSendsTheCustomerBackToTheShippingStepForAnUnresolvableOption(string $shippingOption): void
    {
        $this->startCheckoutSession([
            'tx_products_checkout_payment' => 'invoice',
            'tx_products_checkout_shipping_option' => $shippingOption,
        ]);

        $response = $this->executeFrontendSubRequest($this->checkoutRequest(['action' => 'review']));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=shippingMethod', urldecode($response->getHeaderLine('location')));
    }

    public static function unresolvableShippingOptionProvider(): \Generator
    {
        yield 'option no longer offered for the basket' => [
            'shippingOption' => 'tablerate:999',
        ];
        yield 'shipping step skipped' => [
            'shippingOption' => '',
        ];
    }

    #[Test]
    public function finalizeSendsTheCustomerBackToTheShippingStepWithoutAChosenOption(): void
    {
        $this->startCheckoutSession(['tx_products_checkout_payment' => 'invoice']);

        $response = $this->executeFrontendSubRequest($this->finalizeRequest());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=shippingMethod', urldecode($response->getHeaderLine('location')));
        $this->assertSame(0, $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order')->count('*', 'tx_products_domain_model_order', []));
    }

    /**
     * @param array<string, string> $arguments
     */
    #[Test]
    #[DataProvider('unofferedPaymentMethodProvider')]
    public function submitPaymentSendsTheCustomerBackForAMethodThatIsNotOffered(array $arguments): void
    {
        $this->startCheckoutSession([]);

        $response = $this->executeFrontendSubRequest($this->checkoutRequest(['action' => 'submitPayment'] + $arguments));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=payment', urldecode($response->getHeaderLine('location')));
        $this->assertSame('', $this->checkoutSessionValue('tx_products_checkout_payment'));
    }

    public static function unofferedPaymentMethodProvider(): \Generator
    {
        yield 'no method chosen' => [
            'arguments' => [],
        ];
        yield 'registered method that is unavailable for the basket' => [
            'arguments' => ['paymentMethod' => 'fixture-unavailable'],
        ];
        yield 'unregistered method' => [
            'arguments' => ['paymentMethod' => 'no-such-method'],
        ];
    }

    #[Test]
    public function submitPaymentStoresAnOfferedMethod(): void
    {
        $this->startCheckoutSession([]);

        $this->executeFrontendSubRequest($this->checkoutRequest(['action' => 'submitPayment', 'paymentMethod' => 'invoice']));

        $this->assertSame('invoice', $this->checkoutSessionValue('tx_products_checkout_payment'));
    }

    #[Test]
    public function finalizeSendsTheCustomerBackToThePaymentStepForAMethodThatIsNotOffered(): void
    {
        $this->startCheckoutSession([
            'tx_products_checkout_payment' => 'fixture-unavailable',
            'tx_products_checkout_shipping_option' => 'tablerate:1',
        ]);

        $response = $this->executeFrontendSubRequest($this->finalizeRequest());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=payment', urldecode($response->getHeaderLine('location')));
        $this->assertSame(0, $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order')->count('*', 'tx_products_domain_model_order', []));
    }

    #[Test]
    public function finalizeSendsTheCustomerBackToTheAddressStepForAnIncompleteAddress(): void
    {
        $this->startCheckoutSession([
            'tx_products_checkout_address' => serialize(new Address(country: 'DE')),
            'tx_products_checkout_payment' => 'invoice',
            'tx_products_checkout_shipping_option' => 'tablerate:1',
        ]);

        $response = $this->executeFrontendSubRequest($this->finalizeRequest());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=address', urldecode($response->getHeaderLine('location')));
        $this->assertSame(0, $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order')->count('*', 'tx_products_domain_model_order', []));
    }

    #[Test]
    public function submitAddressShowsTheInvalidFieldsAndStoresNothing(): void
    {
        $this->startCheckoutSession(['tx_products_checkout_address' => '']);

        $response = $this->executeFrontendSubRequest($this->submitAddressRequest(
            ['email' => 'not-an-email', 'firstName' => 'Jane', 'lastName' => '', 'street' => '', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
            shipToDifferentAddress: false
        ));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('Please enter a valid email address.', $body);
        $this->assertStringContainsString('Please enter your last name.', $body);
        $this->assertStringContainsString('Please enter your street and house number.', $body);
        $this->assertStringNotContainsString('Please enter your city.', $body);
        $this->assertSame('', $this->checkoutSessionValue('tx_products_checkout_address'));
    }

    #[Test]
    public function submitAddressSendsTheCustomerBackForAnIncompleteDeliveryAddress(): void
    {
        $this->startCheckoutSession(['tx_products_checkout_address' => '']);

        $response = $this->executeFrontendSubRequest($this->submitAddressRequest(
            ['email' => 'buyer@example.com', 'firstName' => 'Jane', 'lastName' => 'Doe', 'street' => 'Main Street 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
            shipToDifferentAddress: true
        ));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=address', urldecode($response->getHeaderLine('location')));
        $this->assertSame('', $this->checkoutSessionValue('tx_products_checkout_address'));
    }

    #[Test]
    public function submitAddressStoresACompleteAddress(): void
    {
        $this->startCheckoutSession(['tx_products_checkout_address' => '']);

        $this->executeFrontendSubRequest($this->submitAddressRequest(
            ['email' => 'buyer@example.com', 'firstName' => 'Jane', 'lastName' => 'Doe', 'street' => 'Main Street 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
            shipToDifferentAddress: false
        ));

        $this->assertStringContainsString('Main Street 1', $this->checkoutSessionValue('tx_products_checkout_address'));
    }

    /**
     * The address step's form, signed with the fixed functional-test encryption key and scraped per core
     * major from a real render of the address action. The delivery address is always left empty.
     *
     * @param array<string, string> $address
     */
    private function submitAddressRequest(array $address, bool $shipToDifferentAddress): InternalRequest
    {
        $tokens = (new Typo3Version())->getMajorVersion() < 14
            ? [
                'referrerArguments' => 'YToxOntzOjY6ImFjdGlvbiI7czo3OiJhZGRyZXNzIjt95b4783fa5f24e7e3ffbc717edb7eb9e7ae1a34b8',
                'referrerRequest' => '{"@extension":"ProductsCore","@controller":"Checkout","@action":"address"}c42b909efea2805fe6c44e014dfe4323f04f5e23',
                'trustedProperties' => '{"address":{"email":1,"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1},"shipToDifferentAddress":1,"deliveryAddress":{"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1},"giftMessage":1}77a0115d5a2c585a6717cc2b68b4d41e06177ad5',
            ]
            : [
                'referrerArguments' => 'YToxOntzOjY6ImFjdGlvbiI7czo3OiJhZGRyZXNzIjt962f37f58913fb200cffaf3201d8cd6dc6dc963579316c325d5b8db7fed113a36',
                'referrerRequest' => '{"@extension":"ProductsCore","@controller":"Checkout","@action":"address"}97a118abff410647ad9fdaf286d12b9fe008fba2cc4fc5fec3f791711aa7bad4',
                'trustedProperties' => '{"address":{"email":1,"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1},"shipToDifferentAddress":1,"deliveryAddress":{"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1},"giftMessage":1}9cb4a0fb95e3e9243e5603078ded8e051cba8953afefb08c181546935bf78f2d',
            ];
        $parsedBody = [
            'tx_productscore_checkout' => [
                '__referrer' => [
                    '@extension' => 'ProductsCore',
                    '@controller' => 'Checkout',
                    '@action' => 'address',
                    'arguments' => $tokens['referrerArguments'],
                    '@request' => $tokens['referrerRequest'],
                ],
                '__trustedProperties' => $tokens['trustedProperties'],
                'address' => $address,
                'shipToDifferentAddress' => $shipToDifferentAddress ? '1' : '',
                'deliveryAddress' => [
                    'firstName' => '',
                    'lastName' => '',
                    'street' => '',
                    'zip' => '',
                    'city' => '',
                    'country' => 'DE',
                ],
                'giftMessage' => '',
            ],
        ];

        return $this->checkoutRequest(['action' => 'submitAddress', 'controller' => 'Checkout'])
            ->withMethod('POST')
            ->withParsedBody($parsedBody)
            ->withBody($this->get(StreamFactoryInterface::class)->createStream(http_build_query($parsedBody)));
    }

    /**
     * The review page's finalize form, signed with the fixed functional-test encryption key - the same
     * tokens {@see \GoldeneZeiten\Products\Core\Tests\Functional\EndToEnd\ConcurrentStockCheckoutFrontendTest}
     * scraped per core major.
     */
    private function finalizeRequest(): InternalRequest
    {
        $tokens = (new Typo3Version())->getMajorVersion() < 14
            ? [
                'referrerArguments' => 'YToxOntzOjY6ImFjdGlvbiI7czo2OiJyZXZpZXciO30=c00eb9b6b946e8aaef9780e313d95770cb6a0ca4',
                'referrerRequest' => '{"@extension":"ProductsCore","@controller":"Checkout","@action":"review"}7ee936d98a790e2b9144ff030bf31a15ba291c45',
                'trustedProperties' => '{"termsAccepted":1}c40101db1a5f068ba6637fbf13e0a35a4fb5c8c5',
            ]
            : [
                'referrerArguments' => 'YToyOntzOjY6ImFjdGlvbiI7czo2OiJyZXZpZXciO3M6MTA6ImNvbnRyb2xsZXIiO3M6ODoiQ2hlY2tvdXQiO30=97627a9eaaf8e6b920ca85a4ff1236e49b514b64306090036d772458caae4478',
                'referrerRequest' => '{"@extension":"ProductsCore","@controller":"Checkout","@action":"review"}4908ac736b93c2a9e0b89fef9369d5f40d729100cf7ec58afd4051335df42011',
                'trustedProperties' => '{"termsAccepted":1}a6f4fec891056adb7746c8ee60bebea2ed2d731ff9df2fe070f81cd93298dc4f',
            ];
        $parsedBody = [
            'tx_productscore_checkout' => [
                '__referrer' => [
                    '@extension' => 'ProductsCore',
                    '@controller' => 'Checkout',
                    '@action' => 'review',
                    'arguments' => $tokens['referrerArguments'],
                    '@request' => $tokens['referrerRequest'],
                ],
                'termsAccepted' => '1',
                '__trustedProperties' => $tokens['trustedProperties'],
            ],
        ];

        return $this->checkoutRequest(['action' => 'finalize', 'controller' => 'Checkout'])
            ->withMethod('POST')
            ->withParsedBody($parsedBody)
            ->withBody($this->get(StreamFactoryInterface::class)->createStream(http_build_query($parsedBody)));
    }

    /**
     * Starts an anonymous checkout session holding product 1 in the basket, a complete German address and
     * the given further session keys.
     *
     * @param array<string, string> $sessionData
     */
    private function startCheckoutSession(array $sessionData): void
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
            'ses_data' => serialize($sessionData + [
                'tx_products_basket' => '{"items":[{"productUid":1,"articleUid":null,"quantity":1}]}',
                'tx_products_checkout_address' => serialize($address),
            ]),
        ]);
    }

    private function checkoutSessionValue(string $key): string
    {
        $session = $this->get(SessionManager::class)->getSessionBackend('FE')->get(self::SESSION_ID);
        $sessionData = unserialize((string)$session['ses_data'], ['allowed_classes' => false]);

        return is_array($sessionData) ? (string)($sessionData[$key] ?? '') : '';
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

        return (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters($queryParameters)
            ->withCookieParams(['fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt()]);
    }
}
