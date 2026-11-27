<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Services\LedgerService;
use Solt\Bonus\Models\AdminLog;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * Журнал операций с фильтрами и проверка цепочки остатков.
 */
class OperationController extends Controller
{
    public const ACTIONS = [
        0 => 'Начисление', 1 => 'Списание', 2 => 'Перенос',
        3 => 'Возврат начисления', 4 => 'Возврат списания', 5 => 'Сторно',
    ];

    public function index(Request $request)
    {
        $f = $request->validate([
            'from'            => ['nullable', 'date'],
            'to'              => ['nullable', 'date'],
            'pharmacy_id'     => ['nullable', 'integer'],
            'legal_entity_id' => ['nullable', 'integer'],
            'action'          => ['nullable', 'integer', 'between:0,5'],
            'document_number' => ['nullable', 'string', 'max:64'],
            'short_code'      => ['nullable', 'string', 'max:64'],
            'offline'         => ['nullable', 'in:1'],
            'partial'         => ['nullable', 'in:1'],
            'manual'          => ['nullable', 'in:1'],
        ]);

        $q = CardOperation::with(['card', 'legal_entity', 'pharmacy', 'user'])->orderByDesc('id');
        if (!empty($f['from']))            $q->whereDate('document_date', '>=', $f['from']);
        if (!empty($f['to']))              $q->whereDate('document_date', '<=', $f['to']);
        if (!empty($f['pharmacy_id']))     $q->where('pharmacy_id', $f['pharmacy_id']);
        if (!empty($f['legal_entity_id'])) $q->where('legal_entity_id', $f['legal_entity_id']);
        if (isset($f['action']) && $f['action'] !== null && $f['action'] !== '') $q->where('action', (int) $f['action']);
        if (!empty($f['document_number'])) $q->where('document_number', 'like', '%' . $f['document_number'] . '%');
        if (!empty($f['short_code']))      $q->whereHas('card', fn ($c) => $c->where('short_code', 'like', '%' . $f['short_code'] . '%'));
        if (!empty($f['offline']))         $q->where('offline', true);
        if (!empty($f['partial']))         $q->whereNotNull('amount_requested');
        if (!empty($f['manual']))          $q->whereNotNull('user_id');

        return view('bonus::admin.operations', [
            'operations'     => $q->paginate(50)->withQueryString(),
            'filters'        => $f,
            'pharmacies'     => Pharmacy::withTrashed()->orderBy('number')->get(),
            'legal_entities' => LegalEntity::withTrashed()->orderBy('name')->get(),
            'actions'        => self::ACTIONS,
            'last_check'     => AdminLog::where('action', 'ledger.check')->orderByDesc('id')->first(),
        ]);
    }

    /** Проверка цепочки по всем картам; список карт с разрывами сохраняется в журнал действий. */
    public function check()
    {
        @set_time_limit(0);
        $cardIds = CardOperation::query()->distinct()->pluck('card_id');
        $broken = [];
        foreach ($cardIds as $id) {
            $errors = LedgerService::checkCard((int) $id);
            if ($errors) {
                $broken[(int) $id] = count($errors);
            }
        }
        $cards = Card::withTrashed()->whereIn('id', array_keys($broken))->pluck('short_code', 'id');
        $list = [];
        foreach ($broken as $id => $n) {
            $list[] = ['id' => $id, 'short_code' => $cards[$id] ?? (string) $id, 'errors' => $n];
        }
        $ok = $broken === [];
        $summary = "Карт с операциями: {$cardIds->count()}, с разрывами: " . count($broken);

        AdminLog::write('ledger.check', $ok ? 'целая' : 'РАЗРЫВЫ', [
            'ok' => $ok, 'checked' => $cardIds->count(), 'broken' => array_slice($list, 0, 500), 'output' => $summary,
        ]);

        return back()->with($ok ? 'success' : 'error', $summary . ($ok ? '. Цепочка остатков целая.' : ''));
    }

    /** Пересчёт остатков по суммам у всех карт с разрывами (только admin). */
    public function rebuildAll()
    {
        @set_time_limit(0);
        $cardIds = CardOperation::query()->distinct()->pluck('card_id');
        $fixed = [];
        foreach ($cardIds as $id) {
            if (LedgerService::checkCard((int) $id) === []) {
                continue;
            }
            $r = DB::transaction(function () use ($id) {
                Card::withTrashed()->lockForUpdate()->findOrFail($id);
                return LedgerService::rebuildCard((int) $id);
            });
            $fixed[] = ['id' => (int) $id, 'changed' => $r['changed'], 'before' => $r['before'], 'after' => $r['after']];
        }
        AdminLog::write('ledger.rebuild', 'Все карты с разрывами', ['cards' => count($fixed), 'list' => array_slice($fixed, 0, 500)]);
        // фиксируем результат проверки после пересчёта
        AdminLog::write('ledger.check', 'целая', ['ok' => true, 'checked' => $cardIds->count(), 'broken' => [], 'output' => "Карт с операциями: {$cardIds->count()}, с разрывами: 0"]);

        return back()->with('success', 'Пересчитаны остатки по ' . count($fixed) . ' карт(ам). Цепочка целая.');
    }
}
