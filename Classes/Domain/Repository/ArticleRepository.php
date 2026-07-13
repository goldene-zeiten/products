<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Article;

/**
 * @extends AbstractReadOnlyRepository<Article>
 */
final class ArticleRepository extends AbstractReadOnlyRepository
{
    /**
     * Flat listing of all articles across all products (legacy LISTARTICLES parity).
     *
     * @return Article[]
     */
    public function findAllFlat(): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query->execute()->toArray();
    }
}
