<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Models\AdminLog;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * Обзор: счётчики и состояние системы (живы ли кассы, целы ли остатки, были ли ошибки).
 */
class DashboardController extends Controller
{
    public function index()
    {
        $last = CardOperation::orderByDesc('id')->first();
        $today = CardOperation::whereDate('created_at', today());

        return view('bonus::admin.dashboard', [
            'counts' => [
                'legal_entities' => LegalEntity::count(),
                'pharmacies'     => Pharmacy::count(),
                'cards'          => Card::count(),
                'operations'     => CardOperation::count(),
            ],
            'last_operation' => $last,
            'today' => [
                'count'    => (clone $today)->count(),
                'increase' => (float) (clone $today)->where('amount', '>', 0)->sum('amount'),
                'decrease' => (float) (clone $today)->where('amount', '<', 0)->sum('amount'),
                'offline'  => (clone $today)->where('offline', true)->count(),
                'partial'  => (clone $today)->whereNotNull('amount_requested')->count(),
            ],
            'negative'     => (function () {
                $r = DB::selectOne('SELECT COUNT(*) AS cnt, COALESCE(SUM(balance), 0) AS total FROM (SELECT card_id, SUM(amount) AS balance FROM card_operations GROUP BY card_id HAVING SUM(amount) < -0.004) t');
                return ['count' => (int) $r->cnt, 'total' => (float) $r->total];
            })(),
            'last_check'   => AdminLog::where('action', 'ledger.check')->orderByDesc('id')->first(),
            'last_token'   => DB::table('oauth_access_tokens')->max('created_at'),
            'log_errors'   => self::recentLogErrors(),
            'recent_admin' => AdminLog::orderByDesc('id')->limit(8)->get(),
        ]);
    }

    /**
     * Ошибки из storage/logs за последние сутки (последние строки с .ERROR).
     * @return array{count:int, lines:string[]}
     */
    public static function recentLogErrors(int $max = 5): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        if (!$files) {
            return ['count' => 0, 'lines' => []];
        }
        rsort($files);
        $since = now()->subDay();
        $count = 0;
        $lines = [];
        foreach (array_slice($files, 0, 2) as $file) {
            $size = filesize($file);
            $h = fopen($file, 'r');
            if ($size > 512 * 1024) {
                fseek($h, -512 * 1024, SEEK_END);
                fgets($h); // неполная строка
            }
            while (($line = fgets($h)) !== false) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\][^.]*\.ERROR: (.*)$/', $line, $m) && $m[1] >= $since->format('Y-m-d H:i:s')) {
                    $count++;
                    $lines[] = $m[1] . ' ' . mb_substr($m[2], 0, 200);
                }
            }
            fclose($h);
        }
        return ['count' => $count, 'lines' => array_slice(array_reverse($lines), 0, $max)];
    }
}
