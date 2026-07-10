<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\OrderMailService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use GoldeneZeiten\Products\Tests\Functional\Fixtures\TestMailer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Part\DataPart;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;

final class OrderMailServiceTest extends AbstractFrontendTestCase
{
    private OrderMailService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        TestMailer::reset();
        $this->subject = $this->get(OrderMailService::class);
    }

    #[Test]
    public function sendOrderConfirmationSendsMailToCustomer(): void
    {
        $order = $this->buildOrder();

        $this->subject->sendOrderConfirmation($order);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('shopper@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        self::assertStringContainsString('thank you for your order ORD-1', (string)$sentEmails[0]->getTextBody());
    }

    #[Test]
    public function sendMerchantNotificationIsSkippedWithoutConfiguredRecipient(): void
    {
        $this->subject->sendMerchantNotification($this->buildOrder());

        self::assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendMerchantNotificationSendsMailWhenRecipientIsConfigured(): void
    {
        $this->writeSiteConfiguration(
            'products-with-merchant-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
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

        $this->subject->sendMerchantNotification($order);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('merchant@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
    }

    #[Test]
    public function sendOrderConfirmationUsesOverriddenPartialFromFixtureExtension(): void
    {
        $this->writeSiteConfiguration(
            'products-with-partial-override',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => [
                            // Fluid's TemplatePaths resolves partials by walking root paths from the
                            // last entry to the first (later entries act as overlays), so the overriding
                            // path must be listed last.
                            'partialRootPaths' => [
                                'EXT:products/Resources/Private/Partials/',
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

        $this->subject->sendOrderConfirmation($order);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertStringContainsString('FIXTURE-OVERRIDDEN-SIGNATURE', (string)$sentEmails[0]->getHtmlBody());
    }

    #[Test]
    public function sendOrderStatusChangedSendsMailToCustomerByDefault(): void
    {
        $this->subject->sendOrderStatusChanged($this->buildOrder(), OrderStatus::NEW, OrderStatus::CONFIRMED);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('shopper@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        self::assertStringContainsString('new', (string)$sentEmails[0]->getTextBody());
        self::assertStringContainsString('confirmed', (string)$sentEmails[0]->getTextBody());
    }

    #[Test]
    public function sendOrderStatusChangedIsSkippedWhenDisabled(): void
    {
        $this->writeSiteConfiguration(
            'products-with-status-changed-disabled',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
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

        $this->subject->sendOrderStatusChanged($order, OrderStatus::NEW, OrderStatus::CONFIRMED);

        self::assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendWithdrawalNotificationIsSkippedWithoutConfiguredRecipient(): void
    {
        $this->subject->sendWithdrawalNotification($this->buildOrder(), 'Changed my mind');

        self::assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendWithdrawalNotificationSendsMailWithTheReasonWhenRecipientIsConfigured(): void
    {
        $this->writeSiteConfiguration(
            'products-with-withdrawal-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
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

        $this->subject->sendWithdrawalNotification($order, 'Changed my mind');

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('merchant@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('ORD-1', (string)$sentEmails[0]->getSubject());
        self::assertStringContainsString('Changed my mind', (string)$sentEmails[0]->getTextBody());
    }

    #[Test]
    public function sendLowStockWarningIsSkippedWithoutConfiguredRecipient(): void
    {
        $this->subject->sendLowStockWarning('Red Shoes', 2);

        self::assertCount(0, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendLowStockWarningSendsMailWhenRecipientIsConfigured(): void
    {
        // sendLowStockWarning() has no order/site context of its own, so it always resolves
        // settings from the first configured site - overwrite the base "products" site (written
        // by AbstractFrontendTestCase::setUp()) rather than adding a second site that might not
        // be the one getDefaultSettings() picks.
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $this->subject->sendLowStockWarning('Red Shoes', 2);

        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('merchant@example.com', $sentEmails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('Red Shoes', (string)$sentEmails[0]->getSubject());
        self::assertStringContainsString('2', (string)$sentEmails[0]->getTextBody());
    }

    #[Test]
    public function sendMerchantNotificationRoutesToCategoryRecipientInAdditionToTheGlobalOne(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/category_notification.csv');
        $this->writeSiteConfiguration(
            'products-with-category-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'shop@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-category-notification');
        $item = new OrderItem();
        $item->setProduct(1);
        $order->getItems()->attach($item);

        $this->subject->sendMerchantNotification($order);

        $sentEmails = TestMailer::getSentEmails();
        $recipients = array_map(static fn($email): string => $email->getTo()[0]->getAddress(), $sentEmails);
        self::assertCount(2, $sentEmails);
        self::assertContains('shop@example.com', $recipients);
        self::assertContains('category@example.com', $recipients);
    }

    #[Test]
    public function sendMerchantNotificationSendsOnlyOnceWhenCategoryRecipientMatchesTheGlobalOne(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/category_notification.csv');
        $this->writeSiteConfiguration(
            'products-with-matching-category-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'category@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-matching-category-notification');
        $item = new OrderItem();
        $item->setProduct(1);
        $order->getItems()->attach($item);

        $this->subject->sendMerchantNotification($order);

        self::assertCount(1, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendMerchantNotificationRoutesToShippingPointRecipientInAdditionToTheGlobalOne(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shipping_point_notification.csv');
        $this->writeSiteConfiguration(
            'products-with-shipping-point-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'shop@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-shipping-point-notification');
        $item = new OrderItem();
        $item->setProduct(1);
        $order->getItems()->attach($item);

        $this->subject->sendMerchantNotification($order);

        $sentEmails = TestMailer::getSentEmails();
        $recipients = array_map(static fn($email): string => $email->getTo()[0]->getAddress(), $sentEmails);
        self::assertCount(2, $sentEmails);
        self::assertContains('shop@example.com', $recipients);
        self::assertContains('shippingpoint@example.com', $recipients);
    }

    #[Test]
    public function sendMerchantNotificationSendsOnlyOnceWhenShippingPointRecipientMatchesTheGlobalOne(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shipping_point_notification.csv');
        $this->writeSiteConfiguration(
            'products-with-matching-shipping-point-notification',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'shippingpoint@example.com'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );

        $order = $this->buildOrder();
        $order->setSiteIdentifier('products-with-matching-shipping-point-notification');
        $item = new OrderItem();
        $item->setProduct(1);
        $order->getItems()->attach($item);

        $this->subject->sendMerchantNotification($order);

        self::assertCount(1, TestMailer::getSentEmails());
    }

    #[Test]
    public function sendOrderConfirmationAttachesTheConfiguredAgbFile(): void
    {
        $file = $this->createFileInNewLocalStorage('agb.pdf', '%PDF-1.4 fixture content');
        $this->writeSiteConfiguration(
            'products-with-agb-attachment',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
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

        $this->subject->sendOrderConfirmation($order);

        self::assertContains('agb.pdf', $this->attachmentFilenamesOfFirstSentEmail());
    }

    #[Test]
    public function sendOrderConfirmationSkipsTheAgbAttachmentWhenNotConfigured(): void
    {
        $this->subject->sendOrderConfirmation($this->buildOrder());

        self::assertNotContains('agb.pdf', $this->attachmentFilenamesOfFirstSentEmail());
    }

    /**
     * @return array<string|null>
     */
    private function attachmentFilenamesOfFirstSentEmail(): array
    {
        $sentEmails = TestMailer::getSentEmails();
        self::assertCount(1, $sentEmails);
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
        self::assertInstanceOf(ResourceStorage::class, $storage);
        $folder = $storage->getRootLevelFolder();
        $file = $folder->createFile($fileName);
        $file->setContents($contents);
        return $file;
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
