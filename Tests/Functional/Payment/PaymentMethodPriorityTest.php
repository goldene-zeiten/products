<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

final class PaymentMethodPriorityTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/products-payment-fixture'],
                'settings' => [
                    'products' => [],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    public function fixturePaymentMethodIsResolvable(): void
    {
        $registry = $this->get(PaymentMethodRegistry::class);
        $method = $registry->get('fixture-free');

        $this->assertSame('fixture-free', $method->getIdentifier());
    }

    #[Test]
    public function getAvailableReturnsSortedByPriorityDescending(): void
    {
        $this->setUpRequest();
        $registry = $this->get(PaymentMethodRegistry::class);
        $context = new PaymentContext(Money::fromCents(10000), 'EUR', 'DE');
        $available = $registry->getAvailable($context);

        $identifiers = array_map(static fn($method) => $method->getIdentifier(), $available);

        // fixture-preferred has priority 100, should come first
        // fixture-free has priority 0
        // invoice has priority 0
        // fixture-unavailable should not be in the list
        $this->assertContains('fixture-preferred', $identifiers);
        $this->assertContains('fixture-free', $identifiers);
        $this->assertContains('invoice', $identifiers);
        $this->assertNotContains('fixture-unavailable', $identifiers);

        // Find positions and verify order
        $preferredPos = array_search('fixture-preferred', $identifiers, true);
        $freePos = array_search('fixture-free', $identifiers, true);
        $invoicePos = array_search('invoice', $identifiers, true);

        // fixture-preferred (priority 100) should come before fixture-free and invoice (priority 0)
        $this->assertLessThan($freePos, $preferredPos);
        $this->assertLessThan($invoicePos, $preferredPos);
    }

    #[Test]
    public function getAvailableFiltersUnavailableMethods(): void
    {
        $this->setUpRequest();
        $registry = $this->get(PaymentMethodRegistry::class);
        $context = new PaymentContext(Money::fromCents(10000), 'EUR', 'DE');
        $available = $registry->getAvailable($context);

        $identifiers = array_map(static fn($method) => $method->getIdentifier(), $available);

        $this->assertNotContains('fixture-unavailable', $identifiers);
    }

    #[Test]
    public function calculateFeeReturnsCorrectValue(): void
    {
        $registry = $this->get(PaymentMethodRegistry::class);
        $context = new PaymentContext(Money::fromCents(10000), 'EUR', 'DE');

        $surchageMethod = $registry->get('fixture-surcharge');
        $this->assertSame(250, $surchageMethod->calculateFee($context));

        $freeMethod = $registry->get('fixture-free');
        $this->assertSame(0, $freeMethod->calculateFee($context));
    }

    private function setUpRequest(): void
    {
        $request = (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }
}
