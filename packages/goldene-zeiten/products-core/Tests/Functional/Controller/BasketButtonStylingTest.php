<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Every button of the basket is styled as a button, not left to the browser's default look.
 */
final class BasketButtonStylingTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVariantSwitchingTest/product_with_variants.csv');
        $this->importCSVDataSet(__DIR__ . '/../Accessibility/Fixtures/catalog_and_basket.csv');
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize([
                'tx_products_basket' => '{"items":[{"productUid":2,"articleUid":null,"quantity":1}]}',
            ]),
        ]);
    }

    #[Test]
    public function everyBasketSubmitButtonCarriesAButtonClass(): void
    {
        $request = (new InternalRequest('http://localhost/basket'))
            ->withCookieParams(['fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt()]);

        $document = new \DOMDocument();
        @$document->loadHTML((string)$this->executeFrontendSubRequest($request)->getBody());
        $buttons = (new \DOMXPath($document))->query('//form//input[@type="submit"] | //form//button[not(@type) or @type="submit"]');

        $this->assertNotFalse($buttons);
        $this->assertGreaterThan(1, $buttons->length);
        foreach ($buttons as $button) {
            $this->assertInstanceOf(\DOMElement::class, $button);
            $this->assertMatchesRegularExpression(
                '/(^|\s)btn(\s|$)/',
                $button->getAttribute('class'),
                sprintf('The "%s" button has no button class.', $button->getAttribute('value') ?: trim($button->textContent))
            );
        }
    }
}
