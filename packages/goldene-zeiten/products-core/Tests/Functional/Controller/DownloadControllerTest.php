<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
        'fluid_styled_content',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
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
            plugin.tx_productscore.settings.pids.orderHistoryPage = 1
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
    public function guestWithoutAValidHashIsRedirectedToTheOrderHistoryAndSeesNoDownloads(): void
    {
        $request = $this->downloadRequest(2, 'not-a-valid-hash');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('http://localhost/', $response->getHeaderLine('location'));
        $this->assertStringNotContainsString('example.pdf', (string)$response->getBody());
    }

    #[Test]
    public function aRequestWithoutAnOrderAnswersNotFound(): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_download[action]=list');
        $request = (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters([
                'tx_productscore_download[action]' => 'list',
                'cHash' => $cHash,
            ]);

        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function aRequestForAnUnknownOrderAnswersNotFound(): void
    {
        $response = $this->executeFrontendSubRequest($this->downloadRequest(4711, 'not-a-valid-hash'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function loggedInUserCannotSeeAnotherUsersOrderWithoutAValidHash(): void
    {
        $request = $this->downloadRequest(1, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(2)]);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertStringNotContainsString('example.pdf', (string)$response->getBody());
    }

    #[Test]
    public function anUnpaidOrderListsItsFilesWithoutALink(): void
    {
        $request = $this->downloadRequest(1, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);
        $body = (string)$this->executeFrontendSubRequest($request)->getBody();

        $this->assertStringContainsString('example.pdf', $body);
        $this->assertStringNotContainsString('downloads-link', $body);
        $this->assertStringNotContainsString('fileadmin/example.pdf', $body);
        $this->assertStringContainsString('available once your payment has been received', $body);
    }

    #[Test]
    public function aPaidOrderLinksItsFilesToTheDownloadAction(): void
    {
        $request = $this->downloadRequest(3, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);
        $body = (string)$this->executeFrontendSubRequest($request)->getBody();

        $this->assertStringContainsString('downloads-link', $body);
        $this->assertStringContainsString('tx_productscore_download%5Baction%5D=file', $body);
        $this->assertStringNotContainsString('fileadmin/example.pdf', $body);
    }

    #[Test]
    public function theOwnerOfAPaidOrderDownloadsTheFile(): void
    {
        $this->createDownloadFiles();
        $request = $this->fileRequest(3, 601, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);

        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('%PDF-example', (string)$response->getBody());
        $this->assertStringStartsWith('attachment', $response->getHeaderLine('Content-Disposition'));
    }

    #[Test]
    public function aGuestWithTheOrderTokenDownloadsTheFileOfAPaidOrder(): void
    {
        $this->createDownloadFiles();
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(3);
        $this->assertNotNull($order);

        $response = $this->executeFrontendSubRequest($this->fileRequest(3, 601, $this->get(OrderTokenService::class)->generateToken($order)));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('%PDF-example', (string)$response->getBody());
    }

    #[Test]
    #[DataProvider('unreleasedOrderProvider')]
    public function theFileOfAnOrderThatIsNotPaidOrWasCancelledIsNotDelivered(int $order): void
    {
        $this->createDownloadFiles();
        $request = $this->fileRequest($order, 601, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);

        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('%PDF-example', (string)$response->getBody());
    }

    public static function unreleasedOrderProvider(): \Generator
    {
        yield 'payment still open' => [
            'order' => 1,
        ];
        yield 'paid, then cancelled or withdrawn' => [
            'order' => 4,
        ];
        yield 'paid, then refunded' => [
            'order' => 5,
        ];
    }

    #[Test]
    public function aFileOfAProductThatWasNotOrderedIsNotDelivered(): void
    {
        $this->createDownloadFiles();
        $request = $this->fileRequest(3, 602, null)
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);

        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('%PDF-other', (string)$response->getBody());
    }

    #[Test]
    public function aFileRequestWithoutProofOfTheOrderIsSentToTheOrderHistory(): void
    {
        $this->createDownloadFiles();

        $response = $this->executeFrontendSubRequest($this->fileRequest(3, 601, 'not-a-valid-hash'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('http://localhost/', $response->getHeaderLine('location'));
    }

    private function createDownloadFiles(): void
    {
        $this->get(StorageRepository::class)->createLocalStorage('downloads', 'fileadmin/', 'relative');
        GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/fileadmin');
        GeneralUtility::writeFile(Environment::getPublicPath() . '/fileadmin/example.pdf', '%PDF-example');
        GeneralUtility::writeFile(Environment::getPublicPath() . '/fileadmin/other.pdf', '%PDF-other');
    }

    private function fileRequest(int $order, int $file, ?string $hash): InternalRequest
    {
        $queryParameters = [
            'tx_productscore_download[action]' => 'file',
            'tx_productscore_download[file]' => $file,
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
