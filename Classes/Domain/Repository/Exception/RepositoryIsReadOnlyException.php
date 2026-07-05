<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository\Exception;

/**
 * Thrown when a write operation is attempted on a repository whose
 * records are maintained exclusively in the TYPO3 backend.
 */
final class RepositoryIsReadOnlyException extends \RuntimeException {}
