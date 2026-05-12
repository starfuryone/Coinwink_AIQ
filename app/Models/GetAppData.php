<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAppData
{
    // Rates change at most every few minutes via the currency cron, so a short
    // shared cache is safe and avoids hitting the DB on every page load.
    private const RATES_CACHE_KEY = 'cw_data_cur_rates';
    private const RATES_CACHE_TTL = 60;

    public static function get(?int $id_user): array
    {
        $rates = Cache::remember(
            self::RATES_CACHE_KEY,
            self::RATES_CACHE_TTL,
            fn () => DB::table('cw_data_cur_rates')->get()
        );

        if ($id_user) {
            $settings = DB::table('cw_settings')->where('user_ID', '=', $id_user)->first();
            $subs = DB::table('cw_subs')
                ->where('user_ID', $id_user)
                ->select('date_end', 'status', 'plan', 'date_renewed', 'months')
                ->get();

            return [$rates, $settings, $subs];
        }

        return [$rates, null, null];
    }
}
