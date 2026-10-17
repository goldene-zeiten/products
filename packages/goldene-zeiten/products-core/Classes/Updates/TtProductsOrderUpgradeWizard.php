<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use GoldeneZeiten\Products\Core\Backend\StorageFolderResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Event\CollectMigratedOrderItemOptionsEvent;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ArticleMigrationPrerequisite;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ProductMigrationPrerequisite;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Country\Country;
use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrates orders and line items to new domain models. Requires
 * {@see ProductMigrationPrerequisite} and {@see ArticleMigrationPrerequisite} to have run first.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsOrderMigration')]
final class TtProductsOrderUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const LEGACY_TABLE = 'sys_products_orders';
    private const LEGACY_MM_TABLE = 'sys_products_orders_mm_tt_products';
    private const LEGACY_PRODUCT_TABLE = 'tt_products';
    private const LEGACY_ARTICLE_TABLE = 'tt_products_articles';
    private const LOCAL_TABLE = 'tx_products_domain_model_order';
    private const LOCAL_ADDRESS_TABLE = 'tx_products_domain_model_orderaddress';
    private const LOCAL_ITEM_TABLE = 'tx_products_domain_model_orderitem';
    private const LOCAL_PRODUCT_TABLE = 'tx_products_domain_model_product';
    private const LOCAL_ARTICLE_TABLE = 'tx_products_domain_model_article';

    private const SALUTATIONS = ['Mr.', 'Mrs.', 'Company.', 'To'];
    private const NON_ELECTRONIC_PAY_MODES = [0, 1, 3];

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly StorageFolderResolver $storageFolderResolver,
        private readonly CountryProvider $countryProvider,
        private readonly SiteFinder $siteFinder,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
        private readonly ArticleMigrationPrerequisite $articleMigrationPrerequisite,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate sys_products_orders to tx_products_domain_model_order';
    }

    public function getDescription(): string
    {
        return 'Migrates legacy orders, their billing address and line items. Payment and shipping are '
            . 'mapped through migration.orderPaymentMethods and migration.orderShippingOptions, unmapped '
            . 'payments become "invoice", abandoned checkouts are skipped, and line item '
            . 'prices use the current catalog price, since historical prices are not reliably '
            . 'reconstructable from the legacy orderData blob.';
    }

    public function updateNecessary(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::LEGACY_TABLE)) {
            return false;
        }
        return $this->migrationHelper->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE) > 0;
    }

    public function executeUpdate(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::LEGACY_TABLE)) {
            return true;
        }
        if (!$this->prerequisitesFulfilled()) {
            return false;
        }
        $pid = $this->storageFolderResolver->resolve();
        while (($rows = $this->migrationHelper->fetchUnmigratedBatch(self::LEGACY_TABLE, self::LOCAL_TABLE)) !== []) {
            foreach ($rows as $row) {
                $this->migrateOrder($row, $pid);
            }
        }
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
            ProductMigrationPrerequisite::class,
            ArticleMigrationPrerequisite::class,
        ];
    }

    private function prerequisitesFulfilled(): bool
    {
        $fulfilled = true;
        if (!$this->productMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products still has unmigrated rows; run the product migration '
                    . 'wizard (products_ttProductsProductMigration) first.</error>'
            );
            $fulfilled = false;
        }
        if (!$this->articleMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products_articles still has unmigrated rows; run the article migration '
                    . 'wizard (products_ttProductsArticleMigration) first.</error>'
            );
            $fulfilled = false;
        }
        return $fulfilled;
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function migrateOrder(array $legacyRow, int $pid): void
    {
        $legacyUid = (int)$legacyRow['uid'];
        if ($this->isAbandonedCheckout($legacyRow)) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders uid %d is an abandoned checkout without customer, amount and payment, skipped.</comment>',
                $legacyUid
            ));
            $this->migrationHelper->recordMapping(self::LEGACY_TABLE, $legacyUid, self::LOCAL_TABLE, 0);
            return;
        }
        $country = $this->resolveCountry($legacyUid, (string)$legacyRow['country'], $this->billingCountryCode($legacyUid, $legacyRow));
        $addressUid = $this->insertAddress($legacyRow, $pid, $country[0]);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->insert(self::LOCAL_TABLE)->values(
            $this->orderValues($legacyRow, $pid, $legacyUid, $addressUid, $country)
        )->executeStatement();
        $orderUid = (int)$this->connectionPool->getConnectionForTable(self::LOCAL_TABLE)->lastInsertId();
        $this->migrationHelper->recordMapping(self::LEGACY_TABLE, $legacyUid, self::LOCAL_TABLE, $orderUid);
        if (!$this->migrateItems($legacyUid, $orderUid, $pid)) {
            $this->migrateOrderDataItems($legacyUid, $legacyRow, $orderUid, $pid);
        }
    }

    /**
     * @param array<string, mixed> $legacyRow
     * @param array{0: string, 1: string} $country
     * @return array<string, mixed>
     */
    private function orderValues(array $legacyRow, int $pid, int $legacyUid, int $addressUid, array $country): array
    {
        [$status, $paymentStatus] = $this->resolveStatus($legacyUid, (int)$legacyRow['status']);
        $crdate = (int)$legacyRow['crdate'];
        $amountCents = (int)round((float)$legacyRow['amount'] * 100);
        return [
            'pid' => $pid,
            'order_number' => $this->resolveOrderNumber($legacyUid, (string)$legacyRow['tracking_code']),
            'order_date' => $crdate,
            'frontend_user' => (int)$legacyRow['feusers_uid'],
            'email' => (string)$legacyRow['email'],
            'billing_address' => $addressUid,
            'payment_method' => $this->resolvePaymentMethod($legacyUid, $legacyRow),
            ...$this->resolveShipping($legacyRow),
            'payment_status' => $paymentStatus->value,
            'status' => $status->value,
            'invoice_number' => (string)$legacyRow['bill_no'],
            'currency' => 'EUR',
            'total_net' => $amountCents,
            'total_tax' => 0,
            'total_gross' => $amountCents,
            'tax_country' => $country[0],
            'site_identifier' => $this->resolveSiteIdentifier($legacyUid, (int)$legacyRow['pid']),
            'customer_note' => (string)($legacyRow['note'] ?? ''),
            // 0, not null: Extbase's DataMapper reads a 0 timestamp back as a null DateTime.
            'terms_accepted_at' => (int)$legacyRow['agb'] === 1 ? $crdate : 0,
            'legacy_order_data' => $this->buildLegacyOrderData($legacyUid, $legacyRow),
            'legacy_country_name' => $country[1],
        ];
    }

    /**
     * The retention cleanup only ever deletes orders of a known site.
     */
    private function resolveSiteIdentifier(int $legacyUid, int $legacyPid): string
    {
        try {
            return $this->siteFinder->getSiteByPageId($legacyPid)->getIdentifier();
        } catch (SiteNotFoundException) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders uid %d: page %d belongs to no site, so the invoice retention cleanup will not cover this order.</comment>',
                $legacyUid,
                $legacyPid
            ));
            return '';
        }
    }

    private function resolveOrderNumber(int $legacyUid, string $trackingCode): string
    {
        return trim($trackingCode) !== '' ? $trackingCode : sprintf('LEGACY-%d', $legacyUid);
    }

    /**
     * @return array{0: OrderStatus, 1: PaymentStatus}
     */
    private function resolveStatus(int $legacyUid, int $legacyStatus): array
    {
        $mapped = match ($legacyStatus) {
            0, 1, 12 => [OrderStatus::NEW, PaymentStatus::OPEN],
            2, 10, 30, 31, 32, 33, 50, 51, 52, 53, 60 => [OrderStatus::CONFIRMED, PaymentStatus::OPEN],
            11 => [OrderStatus::CONFIRMED, PaymentStatus::PENDING],
            13 => [OrderStatus::CONFIRMED, PaymentStatus::PAID],
            20, 21, 100 => [OrderStatus::SHIPPED, PaymentStatus::PAID],
            101 => [OrderStatus::COMPLETED, PaymentStatus::PAID],
            200 => [OrderStatus::CANCELLED, PaymentStatus::OPEN],
            default => null,
        };
        if ($mapped === null) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders uid %d had unknown status %d, defaulted to new/open.</comment>',
                $legacyUid,
                $legacyStatus
            ));
            return [OrderStatus::NEW, PaymentStatus::OPEN];
        }
        return $mapped;
    }

    /**
     * tt_products tells the TypoScript key of the payment by the leading number of its `payment` text,
     * e.g. "90: PayPal Gateway"; `migration.orderPaymentMethods` maps it. Anything else becomes invoice.
     *
     * @param array<string, mixed> $legacyRow
     */
    private function resolvePaymentMethod(int $legacyUid, array $legacyRow): string
    {
        $payment = trim((string)($legacyRow['payment'] ?? ''));
        if ($payment === '') {
            if (!in_array((int)$legacyRow['pay_mode'], self::NON_ELECTRONIC_PAY_MODES, true)) {
                $this->output->writeln(sprintf(
                    '<comment>sys_products_orders uid %d used electronic pay_mode %d, historic provider is only in legacy_order_data.</comment>',
                    $legacyUid,
                    (int)$legacyRow['pay_mode']
                ));
            }
            return 'invoice';
        }
        $method = $this->migrationConfiguration->getKeyMap('orderPaymentMethods')[$this->legacyKey($payment)] ?? null;
        if ($method === null) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders uid %d used payment "%s", which migration.orderPaymentMethods does not map; it becomes "invoice".</comment>',
                $legacyUid,
                $payment
            ));
            return 'invoice';
        }
        return $method;
    }

    /**
     * The `shipping` text works like the payment's; `migration.orderShippingOptions` maps its key to a
     * provider and, optionally, an option, e.g. "10:tablerate:1, 20:pickup". The legacy title is kept.
     *
     * @param array<string, mixed> $legacyRow
     * @return array{shipping_provider: string, shipping_option: string, shipping_label: string}
     */
    private function resolveShipping(array $legacyRow): array
    {
        $shipping = trim((string)($legacyRow['shipping'] ?? ''));
        $target = $shipping !== '' ? ($this->migrationConfiguration->getKeyMap('orderShippingOptions')[$this->legacyKey($shipping)] ?? '') : '';
        if ($target === '') {
            return ['shipping_provider' => '', 'shipping_option' => '', 'shipping_label' => ''];
        }
        [$provider, $option] = explode(':', $target, 2) + [1 => ''];
        $label = trim(preg_replace('/^\d+\s*:/', '', $shipping) ?? $shipping);
        return ['shipping_provider' => $provider, 'shipping_option' => $option, 'shipping_label' => $label];
    }

    private function legacyKey(string $text): int
    {
        return preg_match('/^(\d+)\s*:/', $text, $matches) === 1 ? (int)$matches[1] : -1;
    }

    /**
     * tt_products creates the order row when a checkout starts; one never finished has no customer, no
     * amount and no payment.
     *
     * @param array<string, mixed> $legacyRow
     */
    private function isAbandonedCheckout(array $legacyRow): bool
    {
        return trim((string)$legacyRow['email']) === ''
            && trim((string)$legacyRow['last_name']) === ''
            && (float)$legacyRow['amount'] === 0.0
            && trim((string)($legacyRow['payment'] ?? '')) === '';
    }

    /**
     * @return array{0: string, 1: string} [resolved ISO alpha-2 code, legacy free-text fallback]
     */
    private function resolveCountry(int $legacyUid, string $legacyCountry, string $countryCode): array
    {
        $byCode = $countryCode !== '' ? $this->countryProvider->getByIsoCode($countryCode) : null;
        if ($byCode !== null) {
            return [$byCode->getAlpha2IsoCode(), ''];
        }
        $needle = trim($legacyCountry);
        if ($needle === '') {
            return ['', ''];
        }
        foreach ($this->countryProvider->getAll() as $country) {
            if ($this->countryMatches($country, $needle)) {
                return [$country->getAlpha2IsoCode(), ''];
            }
        }
        $this->output->writeln(sprintf(
            '<comment>sys_products_orders uid %d: country "%s" not recognized, kept as legacy_country_name.</comment>',
            $legacyUid,
            $needle
        ));
        return ['', $needle];
    }

    /**
     * The country name of an order is in the shop's language; its order data carries the ISO 3166 code of
     * the billing address, which identifies the country in any language.
     *
     * @param array<string, mixed> $legacyRow
     */
    private function billingCountryCode(int $legacyUid, array $legacyRow): string
    {
        $orderData = $this->parseSerializedBlob($legacyUid, $legacyRow['orderData'] ?? null, 'orderData') ?? [];
        $billing = is_array($orderData['billing'] ?? null) ? $orderData['billing'] : [];
        return strtoupper(trim((string)($billing['country_code'] ?? '')));
    }

    private function countryMatches(Country $country, string $needle): bool
    {
        $candidates = [$country->getName(), $country->getOfficialName(), $country->getAlpha2IsoCode(), $country->getAlpha3IsoCode()];
        foreach (array_filter($candidates) as $candidate) {
            if (strcasecmp((string)$candidate, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function insertAddress(array $legacyRow, int $pid, string $countryCode): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_ADDRESS_TABLE);
        $queryBuilder->insert(self::LOCAL_ADDRESS_TABLE)->values([
            'pid' => $pid,
            'address_type' => 'billing',
            'company' => (string)$legacyRow['company'],
            'salutation' => self::SALUTATIONS[(int)$legacyRow['salutation']] ?? '',
            'first_name' => (string)$legacyRow['first_name'],
            'last_name' => (string)$legacyRow['last_name'],
            'street' => (string)$legacyRow['address'],
            'house_number' => (string)$legacyRow['house_no'],
            'zip' => (string)$legacyRow['zip'],
            'city' => (string)$legacyRow['city'],
            'country' => $countryCode,
            'telephone' => (string)$legacyRow['telephone'],
            'vat_id' => (string)$legacyRow['vat_id'],
        ])->executeStatement();
        return (int)$this->connectionPool->getConnectionForTable(self::LOCAL_ADDRESS_TABLE)->lastInsertId();
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function buildLegacyOrderData(int $legacyUid, array $legacyRow): string
    {
        $data = [
            'client_ip' => (string)($legacyRow['client_ip'] ?? ''),
            'order_data' => $this->parseSerializedBlob($legacyUid, $legacyRow['orderData'] ?? null, 'orderData'),
        ];
        return (string)json_encode($data);
    }

    /**
     * unserialize() returns false both on failure and for a serialized `false`; check for the
     * literal `b:0;` payload before treating a false return as an error.
     *
     * @return array<string, mixed>|null
     */
    private function parseSerializedBlob(int $legacyUid, mixed $blob, string $fieldName): ?array
    {
        $raw = (string)($blob ?? '');
        if (trim($raw) === '') {
            return null;
        }
        $parsed = @unserialize($raw, ['allowed_classes' => false]);
        if ($parsed === false && $raw !== serialize(false)) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders uid %d had an unparseable %s blob, discarded.</comment>',
                $legacyUid,
                $fieldName
            ));
            return null;
        }
        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @return bool whether the order had lines in the mm table
     */
    private function migrateItems(int $legacyOrderUid, int $orderUid, int $pid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LEGACY_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('*')->from(self::LEGACY_MM_TABLE)
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->andWhere($queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($legacyOrderUid)))
            ->executeQuery();
        $found = false;
        while ($row = $result->fetchAssociative()) {
            $this->migrateItem($row, $orderUid, $pid);
            $found = true;
        }
        return $found;
    }

    /**
     * tt_products 3.5 often writes no mm rows; the lines then live in orderData/itemArray, keyed by item
     * number, with the product row in `rec`, the article in `rec/ext/tt_products_articles` and the prices
     * actually charged next to it.
     *
     * @param array<string, mixed> $legacyRow
     */
    private function migrateOrderDataItems(int $legacyUid, array $legacyRow, int $orderUid, int $pid): void
    {
        $orderData = $this->parseSerializedBlob($legacyUid, $legacyRow['orderData'] ?? null, 'orderData') ?? [];
        $itemArray = is_array($orderData['itemArray'] ?? null) ? $orderData['itemArray'] : [];
        $itemArray = is_array($itemArray['tt_products'] ?? null) ? $itemArray['tt_products'] : $itemArray;
        foreach ($itemArray as $lines) {
            foreach (is_array($lines) ? $lines : [] as $line) {
                if (is_array($line) && is_array($line['rec'] ?? null)) {
                    $this->migrateOrderDataItem($legacyUid, $line, $orderUid, $pid);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $line
     */
    private function migrateOrderDataItem(int $legacyUid, array $line, int $orderUid, int $pid): void
    {
        $record = $line['rec'];
        $productLocalUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_PRODUCT_TABLE, (int)($record['uid'] ?? 0), self::LOCAL_PRODUCT_TABLE);
        // The line carries everything an order item snapshots, so a product removed since does not lose it.
        if ($productLocalUid === null || $productLocalUid === 0) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders uid %d ordered product uid %d, which no longer exists; the line is kept without a product link.</comment>',
                $legacyUid,
                (int)($record['uid'] ?? 0)
            ));
            $productLocalUid = 0;
        }
        $legacyArticleUid = $productLocalUid !== 0 ? (int)($record['ext']['tt_products_articles'][0]['uid'] ?? 0) : 0;
        $articleLocalUid = $legacyArticleUid > 0
            ? (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_ARTICLE_TABLE, $legacyArticleUid, self::LOCAL_ARTICLE_TABLE)
            : 0;
        $values = $this->orderDataItemValues($line, $articleLocalUid);
        $optionsEvent = new CollectMigratedOrderItemOptionsEvent($record);
        $this->eventDispatcher->dispatch($optionsEvent);
        $lineOptions = array_map(static fn(LineItemOption $option): array => $option->toArray(), $optionsEvent->getOptions());
        $values['options'] = (string)json_encode($lineOptions !== [] ? ['lineOptions' => $lineOptions] : new \stdClass());
        foreach ($optionsEvent->getOptions() as $option) {
            if ($option->isPersonalizing()) {
                $values['withdrawal_exclusion'] = WithdrawalExclusion::PERSONALIZED->value;
            }
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_ITEM_TABLE);
        $queryBuilder->insert(self::LOCAL_ITEM_TABLE)->values([
            'pid' => $pid,
            'parent_order' => $orderUid,
            'product' => $productLocalUid,
            'article' => $articleLocalUid,
            ...$values,
        ])->executeStatement();
    }

    /**
     * The charged prices carry float noise and are rounded to cents; net and VAT of the line follow its gross.
     *
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function orderDataItemValues(array $line, int $articleLocalUid): array
    {
        $record = $line['rec'];
        $article = $articleLocalUid !== 0 ? $this->fetchRow(self::LOCAL_ARTICLE_TABLE, $articleLocalUid, ['title', 'item_number']) : null;
        $lineGross = (int)round((float)($line['totalTax'] ?? 0) * 100);
        $lineNet = (int)round((float)($line['totalNoTax'] ?? 0) * 100);
        return [
            'title' => (string)($record['title'] ?? ''),
            'article_title' => (string)($article['title'] ?? ''),
            'item_number' => (string)($article['item_number'] ?? $record['itemnumber'] ?? ''),
            'quantity' => (int)($line['count'] ?? 0),
            'unit_price_net' => (int)round((float)($line['priceNoTax'] ?? 0) * 100),
            'unit_price_gross' => (int)round((float)($line['priceTax'] ?? 0) * 100),
            'tax_rate' => (float)($line['tax'] ?? 0) / 100,
            'line_total_net' => $lineNet,
            'line_total_tax' => $lineGross - $lineNet,
            'line_total_gross' => $lineGross,
        ];
    }

    /**
     * @param array<string, mixed> $legacyItemRow
     */
    private function migrateItem(array $legacyItemRow, int $orderUid, int $pid): void
    {
        $productLocalUid = $this->migrationHelper->resolveLocalUid(
            self::LEGACY_PRODUCT_TABLE,
            (int)$legacyItemRow['uid_foreign'],
            self::LOCAL_PRODUCT_TABLE
        );
        // An order must be kept as it was placed, so a line of a product removed since stays, unlinked.
        if ($productLocalUid === null || $productLocalUid === 0) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders_mm_tt_products uid %d referenced missing product uid %d; the line is kept without a product link.</comment>',
                (int)$legacyItemRow['uid'],
                (int)$legacyItemRow['uid_foreign']
            ));
            $this->insertItem($legacyItemRow, $orderUid, 0, 0, $pid);
            return;
        }
        $articleLocalUid = $this->resolveArticle((int)$legacyItemRow['uid'], (int)$legacyItemRow['tt_products_articles_uid']);
        $this->insertItem($legacyItemRow, $orderUid, $productLocalUid, $articleLocalUid, $pid);
    }

    private function resolveArticle(int $legacyItemUid, int $legacyArticleUid): int
    {
        if ($legacyArticleUid === 0) {
            return 0;
        }
        $articleLocalUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_ARTICLE_TABLE, $legacyArticleUid, self::LOCAL_ARTICLE_TABLE);
        if ($articleLocalUid === null || $articleLocalUid === 0) {
            $this->output->writeln(sprintf(
                '<comment>sys_products_orders_mm_tt_products uid %d referenced missing article uid %d, kept as product-only.</comment>',
                $legacyItemUid,
                $legacyArticleUid
            ));
            return 0;
        }
        return $articleLocalUid;
    }

    /**
     * @param array<string, mixed> $legacyItemRow
     */
    private function insertItem(array $legacyItemRow, int $orderUid, int $productLocalUid, int $articleLocalUid, int $pid): void
    {
        $quantity = (int)$legacyItemRow['sys_products_orders_qty'];
        $snapshot = $this->currentSnapshot($productLocalUid, $articleLocalUid);
        $unitPriceCents = (int)round($snapshot['price'] * 100);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_ITEM_TABLE);
        $queryBuilder->insert(self::LOCAL_ITEM_TABLE)->values([
            'pid' => $pid,
            'parent_order' => $orderUid,
            'product' => $productLocalUid,
            'article' => $articleLocalUid,
            'title' => $snapshot['title'],
            'article_title' => $articleLocalUid !== 0 ? $snapshot['articleTitle'] : '',
            'item_number' => $snapshot['itemNumber'],
            'quantity' => $quantity,
            'unit_price_net' => $unitPriceCents,
            'unit_price_gross' => $unitPriceCents,
            'line_total_net' => $unitPriceCents * $quantity,
            'line_total_gross' => $unitPriceCents * $quantity,
            'options' => (string)json_encode(['note' => 'Price reconstructed from current catalog price; historical price unavailable.']),
        ])->executeStatement();
    }

    /**
     * @return array{title: string, itemNumber: string, articleTitle: string, price: float}
     */
    private function currentSnapshot(int $productLocalUid, int $articleLocalUid): array
    {
        $product = $this->fetchRow(self::LOCAL_PRODUCT_TABLE, $productLocalUid, ['title', 'item_number', 'price']);
        $article = $articleLocalUid !== 0 ? $this->fetchRow(self::LOCAL_ARTICLE_TABLE, $articleLocalUid, ['title', 'item_number', 'price']) : null;
        return [
            'title' => (string)($product['title'] ?? ''),
            'itemNumber' => (string)($article['item_number'] ?? $product['item_number'] ?? ''),
            'articleTitle' => (string)($article['title'] ?? ''),
            'price' => (float)($article['price'] ?? $product['price'] ?? 0.0),
        ];
    }

    /**
     * @param string[] $fields
     * @return array<string, mixed>
     */
    private function fetchRow(string $table, int $uid, array $fields): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select(...$fields)->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->executeQuery()->fetchAssociative();
        return $row === false ? [] : $row;
    }
}
