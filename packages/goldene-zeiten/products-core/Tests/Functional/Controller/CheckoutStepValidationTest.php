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
