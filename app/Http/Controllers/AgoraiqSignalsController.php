<?php

namespace App\Http\Controllers;

use App\Database\AgoraiqReadOnlyGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only window into the Agoraiq-Signals `signals` table.
 *
 * Coinwink does NOT mirror Agoraiq data; every request reads live from the
 * `agoraiq` Postgres connection (which is itself locked read-only — see
 * App\Database\AgoraiqReadOnlyGuard).
 *
 * The response shape mirrors Agoraiq's own `toResolvedView` in
 * api/src/models/signal.js: free-tier-safe, with entry/stop/targets blanked.
 * Premium gating can be layered on later by reading cw_settings.subs.
 */
class AgoraiqSignalsController extends Controller
{
    private const PUBLIC_SOURCES = ['scanner', 'provider'];
    private const MAX_LIMIT = 50;
    private const DEFAULT_LIMIT = 20;

    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $conn = DB::connection(AgoraiqReadOnlyGuard::CONNECTION);

        $rows = $conn
            ->table('signals')
            ->select([
                'id', 'symbol', 'type', 'direction', 'confidence', 'status',
                'result', 'duration_sec', 'provider', 'source',
                'created_at', 'resolved_at', 'updated_at',
            ])
            ->whereIn('source', self::PUBLIC_SOURCES)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        // Surface the freshness of the upstream feed so the UI can warn
        // users when Agoraiq's scanner is stalled. Cheap aggregate, separate
        // query so it stays correct even when the page is filtered.
        $lastSignalAt = (string) $conn
            ->table('signals')
            ->whereIn('source', self::PUBLIC_SOURCES)
            ->max('created_at');

        return response()->json([
            'signals' => $rows->map(fn ($r) => $this->toResolvedView((array) $r))->values(),
            'last_signal_at' => $lastSignalAt !== '' ? $lastSignalAt : null,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function toResolvedView(array $r): array
    {
        return [
            'id' => $r['id'] ?? null,
            'symbol' => $r['symbol'] ?? null,
            'type' => $r['type'] ?? null,
            'direction' => $r['direction'] ?? null,
            'confidence' => isset($r['confidence']) ? (float) $r['confidence'] : null,
            'status' => $r['status'] ?? 'OPEN',
            'result' => isset($r['result']) ? (float) $r['result'] : null,
            'duration_sec' => $r['duration_sec'] ?? null,
            'provider' => $r['provider'] ?? null,
            'source' => $r['source'] ?? null,
            'created_at' => $r['created_at'] ?? null,
            'resolved_at' => $r['resolved_at'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }
}
