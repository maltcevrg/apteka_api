<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Отчёты: выбор дат и предустановленных периодов.
 * Сами отчёты — по отдельному ТЗ (docs/20); пока страница с выбором периода.
 */
class ReportController extends Controller
{
    public static function presets(): array
    {
        $today = Carbon::today();
        return [
            'today'       => ['Сегодня',          $today->copy(),                         $today->copy()],
            'yesterday'   => ['Вчера',            $today->copy()->subDay(),               $today->copy()->subDay()],
            'this_week'   => ['Текущая неделя',   $today->copy()->startOfWeek(),          $today->copy()],
            'this_month'  => ['Текущий месяц',    $today->copy()->startOfMonth(),         $today->copy()],
            'last_month'  => ['Прошлый месяц',    $today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter'     => ['Текущий квартал',  $today->copy()->startOfQuarter(),       $today->copy()],
            'last_quarter'=> ['Прошлый квартал',  $today->copy()->subQuarter()->startOfQuarter(), $today->copy()->subQuarter()->endOfQuarter()],
            'year'        => ['Текущий год',      $today->copy()->startOfYear(),          $today->copy()],
        ];
    }

    public function index(Request $request)
    {
        $presets = self::presets();
        $preset = $request->input('preset');
        if ($preset && isset($presets[$preset])) {
            [, $from, $to] = $presets[$preset];
        } else {
            $from = $request->input('from') ? Carbon::parse($request->input('from')) : Carbon::today()->startOfMonth();
            $to   = $request->input('to') ? Carbon::parse($request->input('to')) : Carbon::today();
        }

        return view('bonus::admin.reports', [
            'presets' => $presets,
            'preset'  => $preset,
            'from'    => $from->format('Y-m-d'),
            'to'      => $to->format('Y-m-d'),
        ]);
    }
}
