<?php

declare(strict_types=1);

use App\Exceptions\PlayerException;
use App\Services\Player\PlayerStore;
use CodeIgniter\Database\MySQLi\Connection;
use CodeIgniter\Test\CIUnitTestCase;

final class PlayerStoreTest extends CIUnitTestCase
{
    public function testInvalidCalendarDatesAreRejected(): void
    {
        $this->assertSame('2024-02-29 12:00:00.100000', PlayerStore::date('2024-02-29T12:00:00.1Z'));
        foreach (['2026-02-30T12:00:00Z', '2026-01-01T24:60:00Z', '2026-01-01T12:00:00+05:00'] as $date) {
            try { PlayerStore::date($date); $this->fail('Invalid date accepted'); }
            catch (PlayerException $error) { $this->assertSame('invalid_progress', $error->errorCode); }
        }
    }

    public function testDeadlocksRetryOnlyAfterRollbackAndAreBounded(): void
    {
        $db = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()
            ->onlyMethods(['transBegin', 'transCommit', 'transRollback', 'error'])->getMock();
        $db->method('transBegin')->willReturn(true);
        $db->method('error')->willReturn(['code' => 0, 'message' => '']);
        $db->expects($this->exactly(4))->method('transRollback')->willReturn(true);
        $db->expects($this->once())->method('transCommit')->willReturn(true);
        $store = new PlayerStore($db);
        $tries = 0;
        $this->assertSame('committed', $store->transaction(static function () use (&$tries): string {
            if (++$tries === 1) throw new RuntimeException('Synthetic deadlock', 1213);
            return 'committed';
        }));
        $this->assertSame(2, $tries);
        $tries = 0;
        try {
            $store->transaction(static function () use (&$tries): never { $tries++; throw new RuntimeException('Synthetic lock timeout', 1205); });
            $this->fail('Retry loop did not stop');
        } catch (RuntimeException $error) { $this->assertSame(1205, $error->getCode()); }
        $this->assertSame(3, $tries);
    }
}
