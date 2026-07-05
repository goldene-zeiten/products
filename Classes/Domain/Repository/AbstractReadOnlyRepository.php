<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Repository\Exception\RepositoryIsReadOnlyException;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Base class for repositories whose records are maintained exclusively
 * in the TYPO3 backend. All write operations fail with a descriptive
 * exception.
 *
 * @template T of DomainObjectInterface
 * @extends Repository<T>
 */
abstract class AbstractReadOnlyRepository extends Repository
{
    /**
     * @param object $object
     */
    public function add($object): void
    {
        throw new RepositoryIsReadOnlyException(
            sprintf('"%s" is read-only, records must be added via the TYPO3 backend.', static::class),
            1751741001
        );
    }

    /**
     * @param object $modifiedObject
     */
    public function update($modifiedObject): void
    {
        throw new RepositoryIsReadOnlyException(
            sprintf('"%s" is read-only, records must be updated via the TYPO3 backend.', static::class),
            1751741002
        );
    }

    /**
     * @param object $object
     */
    public function remove($object): void
    {
        throw new RepositoryIsReadOnlyException(
            sprintf('"%s" is read-only, records must be deleted via the TYPO3 backend.', static::class),
            1751741003
        );
    }

    public function removeAll(): void
    {
        throw new RepositoryIsReadOnlyException(
            sprintf('"%s" is read-only, records must be deleted via the TYPO3 backend.', static::class),
            1751741004
        );
    }
}
