<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class DownloadControllerTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $coreExtensionsToLoad = [
        'install',
        'fluid_styled_content',
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadControllerTest/products_with_downloads.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadControllerTest/orders_with_downloads.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadControllerTest/download_content.csv');

        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products_core/Configuration/TypoScript/setup.typoscript',
                'EXT:products_core/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 400
            }
        ');
    }

    #[Test]
    public function ownerSeesTheirOrdersDownloads(): void
    {
        $request = $this->downloadRequest(1, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Download Product', $body);
        $this->assertStringContainsString('example.pdf', $body);
    }

    #[Test]
    public function guestWithAValidHashSeesTheDownloads(): void
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(2);
        $this->assertNotNull($order);
        $hash = $this->get(OrderTokenService::class)->generateToken($order);

        $request = $this->downloadRequest(2, $hash);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Download Product', $body);
        $this->assertStringContainsString('example.pdf', $body);
    }

    #[Test]
    public function guestWithoutAValidHashIsRedirectedAndSeesNoDownloads(): void
    {
        // The redirect() only affects the embedded plugin content, not the outer page's HTTP
        // status (the page around it still renders normally) - so the only reliable signal
        // here is that the download listing itself never leaks into the response body.
        $request = $this->downloadRequest(2, 'not-a-valid-hash');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertStringNotContainsString('example.pdf', (string)$response->getBody());
    }

    #[Test]
    public function loggedInUserCannotSeeAnotherUsersOrderWithoutAValidHash(): void
    {
        $request = $this->downloadRequest(1, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(2)]);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertStringNotContainsString('example.pdf', (string)$response->getBody());
    }

    private function downloadRequest(int $order, ?string $hash): InternalRequest
    {
        $queryParameters = [
            'tx_productscore_download[order]' => $order,
        ];
        if ($hash !== null) {
            $queryParameters['tx_productscore_download[hash]'] = $hash;
        }

        $parameterString = '&id=2';
        foreach ($queryParameters as $key => $value) {
            $parameterString .= '&' . $key . '=' . $value;
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($parameterString);

        return (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters(array_merge($queryParameters, ['cHash' => $cHash]));
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
