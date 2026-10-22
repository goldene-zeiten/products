<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Sdk;

use GoldeneZeiten\Products\Mcp\Sdk\SessionStoreFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * The MCP sessions live in the table of the "products_mcp" session backend and expire after the lifetime.
 */
final class Typo3SessionStoreTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-mcp',
    ];

    #[Test]
    public function aWrittenSessionIsReadBackAndUpdatedInPlace(): void
    {
        $store = $this->get(SessionStoreFactory::class)->create(3600);
        $id = Uuid::v4();

        $this->assertFalse($store->exists($id));
        $this->assertTrue($store->write($id, '{"a":1}'));
        $this->assertTrue($store->write($id, '{"a":2}'));

        $this->assertTrue($store->exists($id));
        $this->assertSame('{"a":2}', $store->read($id));
        $this->assertSame(1, $this->countRows());
    }

    #[Test]
    public function aDestroyedSessionIsGone(): void
    {
        $store = $this->get(SessionStoreFactory::class)->create(3600);
        $id = Uuid::v4();
        $store->write($id, '{}');

        $this->assertTrue($store->destroy($id));

        $this->assertFalse($store->exists($id));
        $this->assertFalse($store->read($id));
    }

    #[Test]
    public function aSessionBeyondTheLifetimeIsGoneAndCollected(): void
    {
        $store = $this->get(SessionStoreFactory::class)->create(3600);
        $id = Uuid::v4();
        $store->write($id, '{}');
        $this->getConnectionPool()->getConnectionForTable('tx_productsmcp_session')
            ->update('tx_productsmcp_session', ['ses_tstamp' => time() - 7200], []);

        $this->assertFalse($store->exists($id));
        $this->assertFalse($store->read($id));

        $store->gc();

        $this->assertSame(0, $this->countRows());
    }

    private function countRows(): int
    {
        return $this->getConnectionPool()->getConnectionForTable('tx_productsmcp_session')
            ->count('*', 'tx_productsmcp_session', []);
    }
}
