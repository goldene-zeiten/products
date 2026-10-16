<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Domain\Repository;

use GoldeneZeiten\Products\Personalization\Domain\Model\Option;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Option>
 */
final class OptionRepository extends Repository
{
    /**
     * The options offered for a basket line: the product's, where an option of the article with the same
     * identifier replaces the product's and further article options are added.
     *
     * @return Option[] keyed by identifier
     */
    public function findForLine(int $productUid, ?int $articleUid): array
    {
        $options = [];
        foreach ($this->findByParent('product', $productUid) as $option) {
            $options[$option->getIdentifier()] = $option;
        }
        if ($articleUid !== null && $articleUid > 0) {
            foreach ($this->findByParent('article', $articleUid) as $option) {
                $options[$option->getIdentifier()] = $option;
            }
        }
        return $options;
    }

    /**
     * @return iterable<Option>
     */
    private function findByParent(string $field, int $uid): iterable
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query
            ->matching($query->equals($field, $uid))
            ->setOrderings(['sorting' => QueryInterface::ORDER_ASCENDING])
            ->execute();
    }
}
