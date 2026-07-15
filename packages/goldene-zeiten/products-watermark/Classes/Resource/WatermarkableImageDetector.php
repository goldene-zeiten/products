<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Watermark\Resource;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Decides whether a FAL file is used as a product, article or category image and therefore should
 * be watermarked.
 *
 * The check is a single lookup in sys_file_reference restricted to the image fields of the three
 * catalog tables. The download file field those tables also carry uses a different field name and is
 * therefore excluded, so downloads are never watermarked.
 */
final readonly class WatermarkableImageDetector
{
    /**
     * Table => image field name(s) that count as a product image.
     */
    private const IMAGE_RELATIONS = [
        'tx_products_domain_model_product' => 'images',
        'tx_products_domain_model_article' => 'images',
        'tx_products_domain_model_category' => 'image',
    ];

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function isWatermarkable(int $fileUid): bool
    {
        if ($fileUid <= 0) {
            return false;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $expr = $queryBuilder->expr();

        $relationConstraints = [];
        foreach (self::IMAGE_RELATIONS as $table => $field) {
            $relationConstraints[] = $expr->and(
                $expr->eq('tablenames', $queryBuilder->createNamedParameter($table)),
                $expr->eq('fieldname', $queryBuilder->createNamedParameter($field)),
            );
        }

        $count = $queryBuilder
            ->count('uid')
            ->from('sys_file_reference')
            ->where(
                $expr->eq('uid_local', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)),
                $expr->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $expr->or(...$relationConstraints),
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$count > 0;
    }
}
