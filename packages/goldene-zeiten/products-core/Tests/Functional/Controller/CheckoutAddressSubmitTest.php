<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Security\Nonce;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Submitting the address step with the shop's configured contact fields, the comment and the desired
 * delivery date: required fields are enforced, a past date is refused, everything else is kept for the
 * order.
 */
final class CheckoutAddressSubmitTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'dddddddddddddddddddddddddddddddd';

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
                        'checkout' => [
                            'fields' => [
                                'telephone' => 'required',
                                'company' => 'hidden',
                                'vatId' => 'optional',
                            ],
                            'allowedCountries' => 'DE,AT,FR',
                            'desiredDeliveryDate' => true,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->startCheckoutSession();
    }

    #[Test]
    public function theContactFieldsTheCommentAndTheDesiredDateAreKeptForTheOrder(): void
    {
        $response = $this->executeFrontendSubRequest($this->submitAddressRequest(
            $this->address(telephone: '+49 30 123456'),
            customerNote: 'Engrave "Forever" inside the ring.',
            desiredDeliveryDate: '2030-12-23',
        ));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=payment', urldecode($response->getHeaderLine('location')));
        $address = unserialize($this->checkoutSessionValue('tx_products_checkout_address'), ['allowed_classes' => [Address::class]]);
        $this->assertInstanceOf(Address::class, $address);
        $this->assertSame('+49 30 123456', $address->getTelephone());
        $this->assertSame('DE123456789', $address->getVatId());
        $this->assertSame('ms', $address->getSalutation());
        $this->assertSame('Engrave "Forever" inside the ring.', $this->checkoutSessionValue('tx_products_checkout_customer_note'));
        $this->assertSame('2030-12-23', $this->checkoutSessionValue('tx_products_checkout_desired_delivery_date'));
    }

    #[Test]
    public function aRequiredContactFieldIsEnforced(): void
    {
        $response = $this->executeFrontendSubRequest($this->submitAddressRequest($this->address(telephone: '')));

        $this->assertStringContainsString('Please enter your phone number.', (string)$response->getBody());
        $this->assertSame('', $this->checkoutSessionValue('tx_products_checkout_address'));
    }

    #[Test]
    public function aDesiredDeliveryDateInThePastIsRefused(): void
    {
        $response = $this->executeFrontendSubRequest($this->submitAddressRequest(
            $this->address(telephone: '+49 30 123456'),
            desiredDeliveryDate: '2020-01-01',
        ));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('tx_productscore_checkout[action]=address', urldecode($response->getHeaderLine('location')));
        $this->assertSame('', $this->checkoutSessionValue('tx_products_checkout_address'));
        $this->assertSame('', $this->checkoutSessionValue('tx_products_checkout_desired_delivery_date'));
    }

    /**
     * @return array<string, string>
     */
    private function address(string $telephone): array
    {
        return [
            'email' => 'buyer@example.com',
            'salutation' => 'ms',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'street' => 'Main Street 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'telephone' => $telephone,
            'vatId' => 'DE123456789',
        ];
    }

    /**
     * The address form of the field configuration above, signed with the fixed functional-test encryption
     * key.
     *
     * @param array<string, string> $address
     */
    private function submitAddressRequest(array $address, string $customerNote = '', string $desiredDeliveryDate = ''): InternalRequest
    {
        $tokens = (new Typo3Version())->getMajorVersion() < 14
            ? [
                'referrerArguments' => 'YToxOntzOjY6ImFjdGlvbiI7czo3OiJhZGRyZXNzIjt95b4783fa5f24e7e3ffbc717edb7eb9e7ae1a34b8',
                'referrerRequest' => '{"@extension":"ProductsCore","@controller":"Checkout","@action":"address"}c42b909efea2805fe6c44e014dfe4323f04f5e23',
                'trustedProperties' => '{"address":{"email":1,"salutation":1,"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1,"telephone":1,"vatId":1},"shipToDifferentAddress":1,"deliveryAddress":{"salutation":1,"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1,"telephone":1},"customerNote":1,"desiredDeliveryDate":1,"giftMessage":1}02a7ff3c97d9bd08e95081b26c459be73088c78e',
            ]
            : [
                'referrerArguments' => 'YToxOntzOjY6ImFjdGlvbiI7czo3OiJhZGRyZXNzIjt962f37f58913fb200cffaf3201d8cd6dc6dc963579316c325d5b8db7fed113a36',
                'referrerRequest' => '{"@extension":"ProductsCore","@controller":"Checkout","@action":"address"}97a118abff410647ad9fdaf286d12b9fe008fba2cc4fc5fec3f791711aa7bad4',
                'trustedProperties' => '{"address":{"email":1,"salutation":1,"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1,"telephone":1,"vatId":1},"shipToDifferentAddress":1,"deliveryAddress":{"salutation":1,"firstName":1,"lastName":1,"street":1,"zip":1,"city":1,"country":1,"telephone":1},"customerNote":1,"desiredDeliveryDate":1,"giftMessage":1}75e4aa04c4136d0928fa545882914a12cc7468d90e4fc0b5301a461a02151d25',
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
                'shipToDifferentAddress' => '',
                'deliveryAddress' => [
                    'salutation' => '',
                    'firstName' => '',
                    'lastName' => '',
                    'street' => '',
                    'zip' => '',
                    'city' => '',
                    'country' => 'DE',
                    'telephone' => '',
                ],
                'customerNote' => $customerNote,
                'desiredDeliveryDate' => $desiredDeliveryDate,
                'giftMessage' => '',
            ],
        ];

        return $this->checkoutRequest(['action' => 'submitAddress', 'controller' => 'Checkout'])
            ->withMethod('POST')
            ->withParsedBody($parsedBody)
            ->withBody($this->get(StreamFactoryInterface::class)->createStream(http_build_query($parsedBody)));
    }

    private function startCheckoutSession(): void
    {
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize([
                'tx_products_basket' => '{"items":[{"productUid":1,"articleUid":null,"quantity":1}]}',
                'tx_products_checkout_address' => '',
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
