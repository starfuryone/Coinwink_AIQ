<?php

namespace App\Database;

use Illuminate\Database\Connection;
use RuntimeException;
use WeakMap;

/**
 * Enforces a strict read-only contract on the `agoraiq` database connection.
 *
 * Coinwink is a module that lives next to (not inside) Agoraiq-Signals. Any
 * cross-module read goes through the `agoraiq` connection, which must never
 * mutate Agoraiq's data. We layer three guards:
 *
 *   1. The DB role in env should be granted SELECT only. This is the
 *      strongest line of defence and the only one that survives a bypass of
 *      the application layer (e.g. someone running `php artisan tinker`).
 *   2. On the first query of each PDO instance we set the Postgres session
 *      to `default_transaction_read_only = on`, so even a misconfigured role
 *      would have writes rejected at the server.
 *   3. A `beforeExecuting` callback inspects the SQL verb and throws before
 *      the statement reaches the server, surfacing the violation at the
 *      original call site.
 */
class AgoraiqReadOnlyGuard
{
    public const CONNECTION = 'agoraiq';

    private const ALLOWED_VERBS = ['select', 'show', 'describe', 'desc', 'explain', 'with'];

    private static ?WeakMap $sessionInitialised = null;

    public static function install(Connection $connection): void
    {
        $connection->beforeExecuting(static function (string $query, array $bindings, Connection $conn): void {
            self::assertReadOnly($query);
            self::ensureSessionReadOnly($conn);
        });
    }

    public static function assertReadOnly(string $sql): void
    {
        $trimmed = ltrim($sql);
        // Strip a single leading block comment if present (Eloquent prepends none, but be safe).
        if (str_starts_with($trimmed, '/*')) {
            $end = strpos($trimmed, '*/');
            if ($end !== false) {
                $trimmed = ltrim(substr($trimmed, $end + 2));
            }
        }
        if ($trimmed === '') {
            return;
        }
        $verb = strtolower((string) strtok($trimmed, " \t\r\n("));
        if (!in_array($verb, self::ALLOWED_VERBS, true)) {
            throw new RuntimeException(
                "Coinwink: write attempt blocked on read-only `agoraiq` connection (verb: {$verb})."
            );
        }
    }

    private static function ensureSessionReadOnly(Connection $connection): void
    {
        // The session GUC is Postgres-specific. If `agoraiq` is misconfigured
        // (or pointed at a different driver in tests), skip silently — the
        // verb check above is still in force.
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }
        $pdo = $connection->getPdo();
        self::$sessionInitialised ??= new WeakMap();
        if (isset(self::$sessionInitialised[$pdo])) {
            return;
        }
        $pdo->exec('SET SESSION default_transaction_read_only = on');
        self::$sessionInitialised[$pdo] = true;
    }
}
