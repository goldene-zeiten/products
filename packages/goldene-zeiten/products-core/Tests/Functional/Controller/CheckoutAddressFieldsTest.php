<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Which contact fields the address step offers, and which countries, is decided per shop in the site
 * settings.
 */
final class CheckoutAddressFieldsTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');
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
                        'pids' => [
                            'termsPage' => 2,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    #[DataProvider('renderedFieldProvider')]
    public function theAddressStepRendersTheConfiguredFields(string $expectedMarkup): void
    {
        $this->assertStringContainsString($expectedMarkup, $this->renderAddressStep());
    }

    public static function renderedFieldProvider(): \Generator
    {
        yield 'billing telephone, required' => [
            'expectedMarkup' => 'name="tx_productscore_checkout[address][telephone]" value="" required="required"',
        ];
        yield 'billing VAT id' => [
            'expectedMarkup' => 'name="tx_productscore_checkout[address][vatId]"',
        ];
        yield 'billing salutation' => [
            'expectedMarkup' => 'name="tx_productscore_checkout[address][salutation]"',
        ];
        yield 'delivery telephone' => [
            'expectedMarkup' => 'name="tx_productscore_checkout[deliveryAddress][telephone]"',
        ];
        yield 'allowed country beyond the old fixed list' => [
            'expectedMarkup' => '<option value="FR">',
        ];
        yield 'order comment' => [
            'expectedMarkup' => 'name="tx_productscore_checkout[customerNote]"',
        ];
        yield 'desired delivery date' => [
            'expectedMarkup' => 'name="tx_productscore_checkout[desiredDeliveryDate]"',
        ];
    }

    #[Test]
    #[DataProvider('omittedFieldProvider')]
    public function theAddressStepOmitsHiddenFieldsAndOtherCountries(string $unexpectedMarkup): void
    {
        $this->assertStringNotContainsString($unexpectedMarkup, $this->renderAddressStep());
    }

    public static function omittedFieldProvider(): \Generator
    {
        yield 'hidden company' => [
            'unexpectedMarkup' => '[address][company]',
        ];
        yield 'country not allowed' => [
            'unexpectedMarkup' => '<option value="CH">',
        ];
    }

    private function renderAddressStep(): string
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_productscore_checkout[action]=address'
        );
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productscore_checkout[action]' => 'address',
                'cHash' => $cHash,
            ])
        );
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }
}
