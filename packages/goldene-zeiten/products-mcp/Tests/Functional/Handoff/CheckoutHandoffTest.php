<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Handoff;

use GoldeneZeiten\Products\Mcp\Handoff\CheckoutLinkSigner;
use GoldeneZeiten\Products\Mcp\Tests\Functional\Tool\AbstractToolTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The link an assistant hands to the customer puts the assistant's basket into the customer's own frontend
 * session and lands on the checkout.
 */
final class CheckoutHandoffTest extends AbstractToolTestCase
{
    #[Test]
    public function theCheckoutLinkIsSignedAndCarriesTheBasketIntoTheFrontendSession(): void
    {
        $basketToken = $this->basketWithTwoRings();
        $url = $this->startCheckout($basketToken);

        $this->assertStringStartsWith('http://localhost/mcp/checkout?token=', $url);
        $linkToken = $this->tokenOf($url);
        $this->assertSame($basketToken, $this->get(CheckoutLinkSigner::class)->verify($linkToken, time()));

        $response = $this->open($linkToken);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/checkout', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        $this->assertSame(
            [['productUid' => 1, 'articleUid' => null, 'quantity' => 2, 'selections' => []]],
            $this->frontendBasket($response)
        );
    }

    #[Test]
    public function openingTheLinkAgainInTheSameBrowserDoesNotDoubleTheLines(): void
    {
        $linkToken = $this->tokenOf($this->startCheckout($this->basketWithTwoRings()));
        $first = $this->open($linkToken);

        $second = $this->open($linkToken, $this->cookieOf($first));

        $this->assertSame(303, $second->getStatusCode());
        $basket = $this->frontendBasketOf($this->cookieOf($first));
        $this->assertCount(1, $basket);
        $this->assertSame(2, $basket[0]['quantity']);
    }

    #[Test]
    public function aVariantLineKeepsItsArticle(): void
    {
        $basketToken = $this->createBasket();
        $this->callTool('add_to_basket', ['basketToken' => $basketToken, 'product' => 3, 'article' => 71, 'quantity' => 2]);

        $response = $this->open($this->tokenOf($this->startCheckout($basketToken)));

        $this->assertSame(71, $this->frontendBasket($response)[0]['articleUid']);
    }

    #[Test]
    public function aTamperedLinkMergesNothingAndLandsOnTheBasketPage(): void
    {
        $linkToken = $this->tokenOf($this->startCheckout($this->basketWithTwoRings()));
        $tampered = substr($linkToken, 0, -4) . 'beef';

        $response = $this->open($tampered);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/basket', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        $this->assertSame([], $this->frontendBasket($response));
    }

    #[Test]
    public function anExpiredLinkMergesNothingAndLandsOnTheBasketPage(): void
    {
        $basketToken = $this->basketWithTwoRings();
        $expired = $this->get(CheckoutLinkSigner::class)->issue($basketToken, time() - 10);

        $response = $this->open($expired);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/basket', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        $this->assertSame([], $this->frontendBasket($response));
    }

    #[Test]
    public function aLinkWithoutATokenLandsOnTheBasketPage(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/mcp/checkout'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/basket', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
    }

    #[Test]
    public function anEmptyBasketCannotStartACheckout(): void
    {
        $result = $this->callTool('start_checkout', ['basketToken' => $this->createBasket()]);

        $this->assertTrue($result['isError']);
    }

    private function createBasket(): string
    {
        return $this->callTool('create_basket')['data']['basketToken'];
    }

    private function basketWithTwoRings(): string
    {
        $token = $this->createBasket();
        $this->callTool('add_to_basket', ['basketToken' => $token, 'product' => 1, 'quantity' => 2]);

        return $token;
    }

    private function startCheckout(string $basketToken): string
    {
        $result = $this->callTool('start_checkout', ['basketToken' => $basketToken]);
        $this->assertFalse($result['isError'], $result['text']);

        return $result['data']['checkoutUrl'];
    }

    private function tokenOf(string $url): string
    {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        return is_string($query['token'] ?? null) ? $query['token'] : '';
    }

    private function open(string $linkToken, ?string $cookie = null): ResponseInterface
    {
        $request = new InternalRequest('http://localhost/mcp/checkout?' . http_build_query(['token' => $linkToken]));
        if ($cookie !== null) {
            $request = $request->withCookieParams(['fe_typo_user' => $cookie]);
        }

        return $this->executeFrontendSubRequest($request);
    }

    private function cookieOf(ResponseInterface $response): string
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (str_starts_with($header, 'fe_typo_user=')) {
                return explode(';', substr($header, strlen('fe_typo_user=')))[0];
            }
        }

        return '';
    }

    /**
     * The basket in the frontend session the response set the cookie for.
     *
     * @return list<array<string, mixed>>
     */
    private function frontendBasket(ResponseInterface $response): array
    {
        return $this->frontendBasketOf($this->cookieOf($response));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function frontendBasketOf(string $cookie): array
    {
        if ($cookie === '') {
            return [];
        }
        $payload = json_decode((string)base64_decode(strtr(explode('.', $cookie)[1], '-_', '+/')), true);
        $record = $this->get(SessionManager::class)->getSessionBackend('FE')->get((string)$payload['identifier']);
        $data = unserialize((string)$record['ses_data'], ['allowed_classes' => false]);

        return json_decode((string)($data['tx_products_basket'] ?? '{}'), true)['items'] ?? [];
    }
}
