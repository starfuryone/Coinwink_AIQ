<?php

namespace Tests\Feature;

use App\Database\AgoraiqReadOnlyGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Locks in Coinwink's read-only contract against the Agoraiq DB.
 *
 * The test stands up an in-memory SQLite connection registered under the
 * `agoraiq` name, installs the same guard `AppServiceProvider` would, and
 * then exercises every write verb we want blocked. The Postgres-specific
 * session GUC is skipped automatically by the guard for non-pgsql drivers
 * (see AgoraiqReadOnlyGuard::ensureSessionReadOnly).
 */
class AgoraiqReadOnlyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // AppServiceProvider already installed the guard on the production
        // pgsql `agoraiq` connection during app boot. Purge that cached
        // Connection so we can swap in a fresh, callback-free SQLite one for
        // seeding before re-installing the guard.
        DB::purge(AgoraiqReadOnlyGuard::CONNECTION);

        config()->set('database.connections.' . AgoraiqReadOnlyGuard::CONNECTION, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $conn = DB::connection(AgoraiqReadOnlyGuard::CONNECTION);
        $conn->unprepared('CREATE TABLE signals (id INTEGER PRIMARY KEY, symbol TEXT)');
        $conn->unprepared("INSERT INTO signals (id, symbol) VALUES (1, 'BTCUSDT')");

        AgoraiqReadOnlyGuard::install($conn);
    }

    public function test_select_is_allowed(): void
    {
        $rows = DB::connection(AgoraiqReadOnlyGuard::CONNECTION)
            ->table('signals')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('BTCUSDT', $rows[0]->symbol);
    }

    public function test_insert_is_blocked(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('write attempt blocked');

        DB::connection(AgoraiqReadOnlyGuard::CONNECTION)
            ->table('signals')
            ->insert(['id' => 2, 'symbol' => 'ETHUSDT']);
    }

    public function test_update_is_blocked(): void
    {
        $this->expectException(RuntimeException::class);

        DB::connection(AgoraiqReadOnlyGuard::CONNECTION)
            ->table('signals')
            ->where('id', 1)
            ->update(['symbol' => 'XRPUSDT']);
    }

    public function test_delete_is_blocked(): void
    {
        $this->expectException(RuntimeException::class);

        DB::connection(AgoraiqReadOnlyGuard::CONNECTION)
            ->table('signals')
            ->where('id', 1)
            ->delete();
    }

    public function test_raw_ddl_is_blocked(): void
    {
        $this->expectException(RuntimeException::class);

        DB::connection(AgoraiqReadOnlyGuard::CONNECTION)
            ->statement('CREATE TABLE foo (id INTEGER)');
    }

    public function test_unit_assert_read_only_classifies_verbs(): void
    {
        // Allowed
        AgoraiqReadOnlyGuard::assertReadOnly('SELECT 1');
        AgoraiqReadOnlyGuard::assertReadOnly('with x as (select 1) select * from x');
        AgoraiqReadOnlyGuard::assertReadOnly('SHOW TABLES');
        AgoraiqReadOnlyGuard::assertReadOnly('/* hint */ SELECT 2');

        // Blocked
        foreach (['INSERT INTO foo VALUES(1)', 'UPDATE foo SET a=1', 'DELETE FROM foo', 'CREATE TABLE t (a int)', 'DROP TABLE t', 'ALTER TABLE t ADD COLUMN x int'] as $sql) {
            try {
                AgoraiqReadOnlyGuard::assertReadOnly($sql);
                $this->fail("Expected block for: {$sql}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('write attempt blocked', $e->getMessage());
            }
        }
    }
}
