<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Accessibility;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Every visible form control of the catalogue and basket has a label a screen reader announces with it.
 */
final class FormControlLabelTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/ProductVariantSwitchingTest/product_with_variants.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/catalog_and_basket.csv');
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize([
                'tx_products_basket' => '{"items":[{"productUid":2,"articleUid":null,"quantity":1},{"productUid":1,"articleUid":1,"quantity":2}]}',
            ]),
        ]);
    }

    /**
     * @param array<string, int|string> $arguments
     */
    #[Test]
    #[DataProvider('pageProvider')]
    public function everyFormControlHasALabel(string $path, int $pageId, array $arguments, int $expectedControls): void
    {
        $queryParameters = [];
        $hashString = '&id=' . $pageId;
        foreach ($arguments as $name => $value) {
            $queryParameters[$name] = $value;
            $hashString .= '&' . $name . '=' . $value;
        }
        if ($arguments !== []) {
            $queryParameters['cHash'] = $this->get(CacheHashCalculator::class)->generateForParameters($hashString);
        }
        $request = (new InternalRequest('http://localhost' . $path))
            ->withQueryParameters($queryParameters)
            ->withCookieParams(['fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt()]);

        $document = new \DOMDocument();
        @$document->loadHTML((string)$this->executeFrontendSubRequest($request)->getBody());
        $xpath = new \DOMXPath($document);
        $controls = $xpath->query('//select | //textarea | //input[not(@type="hidden") and not(@type="submit")]');

        $this->assertNotFalse($controls);
        $this->assertSame($expectedControls, $controls->length);
        foreach ($controls as $control) {
            $this->assertInstanceOf(\DOMElement::class, $control);
            $id = $control->getAttribute('id');
            $name = $control->getAttribute('name');
            $this->assertNotSame('', $id, sprintf('Control "%s" has no id a label could point to.', $name));
            $labels = $xpath->query(sprintf('//label[@for="%s"]', $id));
            $this->assertNotFalse($labels);
            $this->assertSame(1, $labels->length, sprintf('Control "%s" has no label.', $name));
            $this->assertNotSame('', trim((string)$labels->item(0)?->textContent), sprintf('The label of "%s" is empty.', $name));
        }
    }

    public static function pageProvider(): \Generator
    {
        yield 'product without variants: quantity' => [
            'path' => '/shop',
            'pageId' => 2,
            'arguments' => ['tx_productscore_productdetail[product]' => 2],
            'expectedControls' => 1,
        ];
        yield 'product with a chosen variant: attribute and quantity' => [
            'path' => '/shop',
            'pageId' => 2,
            'arguments' => [
                'tx_productscore_productdetail[product]' => 1,
                'tx_productscore_productdetail[attributeValues][0]' => 1,
            ],
            'expectedControls' => 2,
        ];
        yield 'basket: a quantity per line' => [
            'path' => '/basket',
            'pageId' => 3,
            'arguments' => [],
            'expectedControls' => 2,
        ];
    }
}
