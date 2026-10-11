<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalStatement;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\TestMailer;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Part\DataPart;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;

final class OrderMailServiceTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
    }

    #[Test]
    public function sendOrderConfirmationSendsMailToCustomer(): void
    {
        $subject = $this->get(OrderMailService::class);
        $order = $this->buildOrder();

        $subject->sendOrderConfirmation($order);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('shopper@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        $this->assertStringContainsString('thank you for your order ORD-1', (string)$sentEmails[0]->getTextBody());
    }

    #[Test]
    public function sendOrderConfirmationHasNoBccByDefault(): void
    {
        $subject = $this->get(OrderMailService::class);
        $subject->sendOrderConfirmation($this->buildOrder());

        $this->assertCount(0, TestMailer::getSentEmails()[0]->getBcc());
    }

    #[Test]
    public function sendOrderConfirmationBccsTheConfiguredRecipient(): void
    {
        $subject = $this->get(OrderMailService::class);
        $this->writeSiteConfiguration(
            'products-with-order-bcc',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['orderBccRecipient' => 'accounting@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-order-bcc');

        $subject->sendOrderConfirmation($order);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('accounting@example.com', $sentEmails[0]->getBcc()[0]->getAddress());
    }

    #[Test]
    public function sendOrderConfirmationCarriesTheWithdrawalButtonUrl(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_with_items_and_addresses.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'pids' => ['withdrawalPage' => 2],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $subject = $this->get(OrderMailService::class);
        $order = $this->fetchPersistedOrder(1);
        $order->setOrderDate(new \DateTime());
        $order->setInvoiceNumber('');

        $subject->sendOrderConfirmation($order);

        $html = (string)TestMailer::getSentEmails()[0]->getHtmlBody();
        $this->assertStringContainsString('Withdraw from contract here', $html);
        $this->assertStringContainsString('tx_productscore_withdrawal', $html);
        $this->assertStringContainsString('cHash=', $html);
    }

    #[Test]
    public function sendOrderConfirmationNoticesFullExclusionInsteadOfTheButton(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_with_excluded_items.csv');
        $subject = $this->get(OrderMailService::class);
        $order = $this->fetchPersistedOrder(11);
        $order->setOrderDate(new \DateTime());

        $subject->sendOrderConfirmation($order);

        $email = TestMailer::getSentEmails()[0];
        $this->assertStringContainsString('exempt from the statutory right of withdrawal', (string)$email->getTextBody());
        $this->assertStringNotContainsString('cHash=', (string)$email->getHtmlBody());
    }

    private function fetchPersistedOrder(int $uid): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage($uid);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    #[Test]
    public function sendMerchantNotificationIsSkippedWithoutConfiguredRecipient(): void
    {
        $subject = $this->get(OrderMailService::class);
        $subject->sendMerchantNotification($this->buildOrder());

        $this->assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendMerchantNotificationSendsMailWhenRecipientIsConfigured(): void
    {
        $subject = $this->get(OrderMailService::class);
        $this->writeSiteConfiguration(
            'products-with-merchant-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-merchant-notification');

        $subject->sendMerchantNotification($order);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('merchant@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
    }

    #[Test]
    public function sendOrderConfirmationUsesOverriddenPartialFromFixtureExtension(): void
    {
        $subject = $this->get(OrderMailService::class);
        $this->writeSiteConfiguration(
            'products-with-partial-override',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => [
                            // Later paths in TemplatePaths override earlier ones; list fixtures last.
                            'partialRootPaths' => [
                                'EXT:products_core/Resources/Private/Partials/',
                                'EXT:frontend_test/Resources/Private/Partials/',
                            ],
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-partial-override');

        $subject->sendOrderConfirmation($order);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertStringContainsString('FIXTURE-OVERRIDDEN-SIGNATURE', (string)$sentEmails[0]->getHtmlBody());
    }

    #[Test]
    public function sendOrderStatusChangedSendsMailToCustomerByDefault(): void
    {
        $subject = $this->get(OrderMailService::class);
        $subject->sendOrderStatusChanged($this->buildOrder(), OrderStatus::NEW, OrderStatus::CONFIRMED);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('shopper@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        $this->assertStringContainsString('new', (string)$sentEmails[0]->getTextBody());
        $this->assertStringContainsString('confirmed', (string)$sentEmails[0]->getTextBody());
    }

    #[Test]
    public function sendOrderStatusChangedIsSkippedWhenDisabled(): void
    {
        $subject = $this->get(OrderMailService::class);
        $this->writeSiteConfiguration(
            'products-with-status-changed-disabled',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['orderStatusChanged' => ['enabled' => false]],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-status-changed-disabled');

        $subject->sendOrderStatusChanged($order, OrderStatus::NEW, OrderStatus::CONFIRMED);

        $this->assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendWithdrawalNotificationIsSkippedWithoutConfiguredRecipient(): void
    {
        $subject = $this->get(OrderMailService::class);
        $subject->sendWithdrawalNotification($this->buildOrder(), $this->buildStatement('Changed my mind'), []);

        $this->assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendWithdrawalNotificationSendsMailWithTheReasonWhenRecipientIsConfigured(): void
    {
        $subject = $this->get(OrderMailService::class);
        $this->writeSiteConfiguration(
            'products-with-withdrawal-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-withdrawal-notification');

        $subject->sendWithdrawalNotification($order, $this->buildStatement('Changed my mind'), []);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('merchant@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        $this->assertStringContainsString('Changed my mind', (string)$sentEmails[0]->getTextBody());
    }

    #[Test]
    public function sendLowStockWarningIsSkippedWithoutConfiguredRecipient(): void
    {
        $subject = $this->get(OrderMailService::class);
        $subject->sendLowStockWarning('Red Shoes', 2);

        $this->assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendLowStockWarningSendsMailWhenRecipientIsConfigured(): void
    {
        $subject = $this->get(OrderMailService::class);
        // sendLowStockWarning() has no order/site context of its own, so it always resolves
        // settings from the first configured site - overwrite the base "products" site (written
        // by AbstractFrontendTestCase::setUp()) rather than adding a second site that might not
        // be the one getDefaultSettings() picks.
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $subject->sendLowStockWarning('Red Shoes', 2);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('merchant@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Red Shoes', (string)$sentEmails[0]->getSubject());
        $this->assertStringContainsString('2', (string)$sentEmails[0]->getTextBody());
    }

    /**
     * @param non-empty-string $siteIdentifier
     */
    #[Test]
    #[DataProvider('secondaryRecipientRoutingProvider')]
    public function sendMerchantNotificationRoutesToSecondaryRecipientInAdditionToTheGlobalOne(string $fixturePath, string $siteIdentifier, string $secondaryRecipient): void
    {
        $subject = $this->get(OrderMailService::class);
        $this->importCSVDataSet($fixturePath);
        $this->writeSiteConfiguration(
            $siteIdentifier,
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'shop@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier($siteIdentifier);
        $item = new OrderItem();
        $item->setProduct(1);
        $order->getItems()->attach($item);

        $subject->sendMerchantNotification($order);

        $sentEmails = TestMailer::getSentEmails();
        $recipients = array_map(static fn($email): string => $email->getTo()[0]->getAddress(), $sentEmails);
        $this->assertCount(2, $sentEmails);
        $this->assertContains('shop@example.com', $recipients);
        $this->assertContains($secondaryRecipient, $recipients);
    }

    /**
     * @return \Generator<string, array<string, string>>
     */
    public static function secondaryRecipientRoutingProvider(): \Generator
    {
        yield 'categoryRecipient' => [
            'fixturePath' => __DIR__ . '/Fixtures/OrderMailServiceTest/category_notification.csv',
            'siteIdentifier' => 'products-with-category-notification',
            'secondaryRecipient' => 'category@example.com',
        ];
        yield 'shippingPointRecipient' => [
            'fixturePath' => __DIR__ . '/Fixtures/OrderMailServiceTest/shipping_point_notification.csv',
            'siteIdentifier' => 'products-with-shipping-point-notification',
            'secondaryRecipient' => 'shippingpoint@example.com',
        ];
    }

    /**
     * @param non-empty-string $siteIdentifier
     */
    #[Test]
    #[DataProvider('secondaryRecipientMatchingGlobalOneProvider')]
    public function sendMerchantNotificationSendsOnlyOnceWhenSecondaryRecipientMatchesTheGlobalOne(string $fixturePath, string $siteIdentifier, string $merchantRecipient): void
    {
        $subject = $this->get(OrderMailService::class);
        $this->importCSVDataSet($fixturePath);
        $this->writeSiteConfiguration(
            $siteIdentifier,
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => $merchantRecipient],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier($siteIdentifier);
        $item = new OrderItem();
        $item->setProduct(1);
        $order->getItems()->attach($item);

        $subject->sendMerchantNotification($order);

        $this->assertCount(1, TestMailer::getSentEmails());
    }

    /**
     * @return \Generator<string, array<string, string>>
     */
    public static function secondaryRecipientMatchingGlobalOneProvider(): \Generator
    {
        yield 'categoryRecipientMatchesGlobal' => [
            'fixturePath' => __DIR__ . '/Fixtures/OrderMailServiceTest/category_notification.csv',
            'siteIdentifier' => 'products-with-matching-category-notification',
            'merchantRecipient' => 'category@example.com',
        ];
        yield 'shippingPointRecipientMatchesGlobal' => [
            'fixturePath' => __DIR__ . '/Fixtures/OrderMailServiceTest/shipping_point_notification.csv',
            'siteIdentifier' => 'products-with-matching-shipping-point-notification',
            'merchantRecipient' => 'shippingpoint@example.com',
        ];
    }

    #[Test]
    public function sendOrderConfirmationAttachesTheConfiguredAgbFile(): void
    {
        $subject = $this->get(OrderMailService::class);
        $file = $this->createFileInNewLocalStorage('agb.pdf', '%PDF-1.4 fixture content');
        $this->writeSiteConfiguration(
            'products-with-agb-attachment',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['agbAttachment' => $file->getCombinedIdentifier()],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-agb-attachment');

        $subject->sendOrderConfirmation($order);

        $this->assertContains('agb.pdf', $this->attachmentFilenamesOfFirstSentEmail());
    }

    #[Test]
    public function sendOrderConfirmationSkipsTheAgbAttachmentWhenNotConfigured(): void
    {
        $subject = $this->get(OrderMailService::class);
        $subject->sendOrderConfirmation($this->buildOrder());

        $this->assertNotContains('agb.pdf', $this->attachmentFilenamesOfFirstSentEmail());
    }

    /**
     * @return array<string|null>
     */
    private function attachmentFilenamesOfFirstSentEmail(): array
    {
        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        return array_map(static fn(DataPart $part): ?string => $part->getFilename(), $sentEmails[0]->getAttachments());
    }

    private function createFileInNewLocalStorage(string $fileName, string $contents): File
    {
        $storageRepository = $this->get(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage(
            'agb-test-storage',
            'fileadmin',
            'relative'
        );
        $storage = $storageRepository->findByUid($storageUid);
        $this->assertInstanceOf(ResourceStorage::class, $storage);
        $folder = $storage->getRootLevelFolder();
        $file = $folder->createFile($fileName);
        $file->setContents($contents);
        return $file;
    }

    #[Test]
    public function sendWithdrawalAcknowledgementCarriesTheStatementContentAndTheSubmissionTimeInTheShopTimezone(): void
    {
        $subject = $this->get(OrderMailService::class);
        $order = $this->buildOrder();
        $item = new OrderItem();
        $item->setTitle('Red Shoes');
        $item->setQuantity(2);
        $order->getItems()->attach($item);

        $subject->sendWithdrawalAcknowledgement($order, $this->buildStatement('Too small'), [$item]);

        $sentEmails = TestMailer::getSentEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame('shopper@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        $text = (string)$sentEmails[0]->getTextBody();
        $this->assertStringContainsString('Jane Shopper', $text);
        $this->assertStringContainsString('shopper@example.com', $text);
        $this->assertStringContainsString('Red Shoes (x 2)', $text);
        $this->assertStringContainsString('Too small', $text);
        $this->assertStringContainsString('2026-10-01 12:15:30 CEST', $text);
        $this->assertStringContainsString('2026-10-01 12:15:30 CEST', (string)$sentEmails[0]->getHtmlBody());
    }

    #[Test]
    public function sendWithdrawalAcknowledgementNamesTheReturnAddressAndDeadlineForAShippedOrder(): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'withdrawal' => [
                            'trader' => [
                                'name' => 'Shop GmbH',
                                'address' => "Returns Street 5\n12345 Sampletown",
                            ],
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $subject = $this->get(OrderMailService::class);
        $order = $this->buildOrder();
        $order->setStatus(OrderStatus::WITHDRAWN);

        $subject->sendWithdrawalAcknowledgement($order, $this->buildStatement(''), []);

        $text = (string)TestMailer::getSentEmails()[0]->getTextBody();
        $this->assertStringContainsString('Returns Street 5', $text);
        $this->assertStringContainsString('12345 Sampletown', $text);
        $this->assertStringContainsString('2026-10-15', $text);
        $this->assertStringContainsString('We may withhold the reimbursement', $text);
    }

    #[Test]
    public function sendWithdrawalAcknowledgementAsksForNoReturnOfUnshippedGoods(): void
    {
        $subject = $this->get(OrderMailService::class);
        $order = $this->buildOrder();
        $order->setStatus(OrderStatus::CANCELLED);

        $subject->sendWithdrawalAcknowledgement($order, $this->buildStatement(''), []);

        $text = (string)TestMailer::getSentEmails()[0]->getTextBody();
        $this->assertStringContainsString('had not been shipped yet', $text);
        $this->assertStringNotContainsString('We may withhold the reimbursement', $text);
    }

    #[Test]
    public function sendWithdrawalAcknowledgementWarnsAboutTheSealOfConditionalItems(): void
    {
        $subject = $this->get(OrderMailService::class);
        $order = $this->buildOrder();
        $item = new OrderItem();
        $item->setTitle('Sealed Cream');
        $item->setWithdrawalExclusion(WithdrawalExclusion::SEALED_HYGIENE);

        $subject->sendWithdrawalAcknowledgement($order, $this->buildStatement(''), [$item]);

        $this->assertStringContainsString('not unsealed them after delivery', (string)TestMailer::getSentEmails()[0]->getTextBody());
    }

    #[Test]
    public function sendOrderConfirmationRecordsThatTheWithdrawalInformationWasProvided(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_with_items_and_addresses.csv');
        $subject = $this->get(OrderMailService::class);
        $order = $this->fetchPersistedOrder(1);
        $order->setOrderDate(new \DateTime());
        $order->setInvoiceNumber('');

        $subject->sendOrderConfirmation($order);

        $this->assertNotNull($order->getWithdrawalInformationProvidedAt());
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_order');
        $stored = (int)$queryBuilder->select('withdrawal_information_provided_at')
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        $this->assertGreaterThan(0, $stored);
    }

    private function buildStatement(string $reason): WithdrawalStatement
    {
        return new WithdrawalStatement(
            'Jane Shopper',
            'shopper@example.com',
            $reason,
            null,
            new \DateTimeImmutable('2026-10-01 10:15:30', new \DateTimeZone('UTC'))
        );
    }

    private function buildOrder(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-1');
        $order->setEmail('shopper@example.com');
        $order->setCurrency('EUR');
        $order->setTotalGross(Money::fromCents(1999));
        $order->setSiteIdentifier('products');

        return $order;
    }
}
