<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Sdk;

use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotFoundException;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotUpdatedException;
use TYPO3\CMS\Core\Session\Backend\SessionBackendInterface;

/**
 * Keeps the SDK's MCP sessions in a TYPO3 session backend. A session that was not used for the lifetime is
 * treated as gone, and removed by the garbage collection.
 */
final readonly class Typo3SessionStore implements SessionStoreInterface
{
    public function __construct(
        private SessionBackendInterface $backend,
        private int $lifetime,
        private Context $context
    ) {}

    public function exists(Uuid $id): bool
    {
        return $this->find($id) !== null;
    }

    public function read(Uuid $id): string|false
    {
        $record = $this->find($id);

        return $record === null ? false : (string)$record['ses_data'];
    }

    public function write(Uuid $id, string $data): bool
    {
        $key = $id->toRfc4122();
        try {
            $this->backend->get($key);
            $this->backend->update($key, ['ses_data' => $data]);
        } catch (SessionNotFoundException) {
            return $this->create($key, $data);
        } catch (SessionNotUpdatedException) {
            return false;
        }

        return true;
    }

    public function destroy(Uuid $id): bool
    {
        $this->backend->remove($id->toRfc4122());

        return true;
    }

    /**
     * The backend deletes expired sessions in one statement without telling which, so the list stays empty.
     *
     * @return Uuid[]
     */
    public function gc(): array
    {
        $this->backend->collectGarbage($this->lifetime);

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(Uuid $id): ?array
    {
        try {
            $record = $this->backend->get($id->toRfc4122());
        } catch (SessionNotFoundException) {
            return null;
        }

        return (int)$record['ses_tstamp'] + $this->lifetime < (int)$this->context->getPropertyFromAspect('date', 'timestamp') ? null : $record;
    }

    private function create(string $key, string $data): bool
    {
        try {
            $this->backend->set($key, ['ses_data' => $data]);
        } catch (SessionNotCreatedException) {
            return false;
        }

        return true;
    }
}
