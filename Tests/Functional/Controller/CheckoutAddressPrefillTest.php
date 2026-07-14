<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class CheckoutAddressPrefillTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/checkout_content.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CheckoutAddressPrefillTest/checkout_address_prefill.csv');
    }

    #[Test]
    #[DataProvider('addressActionPrefillProvider')]
    public function addressActionPrefillsFromCustomerData(int $frontendUserUid, string $expectedStreet, string $expectedCity, string $expectedEmail): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_productscore_checkout[action]=address'
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser($frontendUserUid)])
            ->withQueryParameters([
                'tx_productscore_checkout[action]' => 'address',
                'cHash' => $cHash,
            ]);
        $response = $this->executeFrontendSubRequest($request);
        $body = (string)$response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($expectedStreet, $body);
        $this->assertStringContainsString($expectedCity, $body);
        $this->assertStringContainsString($expectedEmail, $body);
    }

    public static function addressActionPrefillProvider(): \Generator
    {
        yield 'returning customer uses last order' => [
            'frontendUserUid' => 1,
            'expectedStreet' => 'Loop Street 42',
            'expectedCity' => 'Repeatville',
            'expectedEmail' => 'returning@example.com',
        ];

        yield 'new customer uses profile data' => [
            'frontendUserUid' => 2,
            'expectedStreet' => 'Profile Street 7',
            'expectedCity' => 'Berlin',
            'expectedEmail' => 'new@example.com',
        ];
    }

    #[Test]
    public function addressActionIsBlankForAnonymousVisitor(): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_productscore_checkout[action]=address'
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters([
                'tx_productscore_checkout[action]' => 'address',
                'cHash' => $cHash,
            ]);
        $response = $this->executeFrontendSubRequest($request);
        $body = (string)$response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Loop Street 42', $body);
    }

    /**
     * @return string the value to use for the `fe_typo_user` cookie
     */
    private function loginFrontendUser(int $frontendUserUid): string
    {
        $sessionId = bin2hex(random_bytes(16));
        $sessionBackend = $this->get(SessionManager::class)->getSessionBackend('FE');
        $sessionBackend->set($sessionId, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => $frontendUserUid,
        ]);

        return UserSession::createFromRecord($sessionId, [])->getJwt();
    }
}
