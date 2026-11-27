<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Models\AdminLog;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Services\LedgerService;

/**
 * Карты: поиск, карточка клиента с балансом и историей, блокировка/замена,
 * ручная корректировка баланса (редактор и администратор).
 */
class CardController extends Controller
{
    public const ADJUST_DOCUMENT_TYPE = 'Корректировка';
    public const REPLACE_DOCUMENT_TYPE = 'Замена карты';

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $cards = null;
        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q);
            $cards = Card::withTrashed()->with('legal_entity')
                ->where(function ($w) use ($q, $digits) {
                    $w->where('short_code', 'like', "%{$q}%")
                      ->orWhere('lastname', 'like', "{$q}%")
                      ->orWhere('guid', $q);
                    if (strlen($digits) >= 5) {
                        $w->orWhere('phone', 'like', "%{$digits}%");
                    }
                })
                ->orderBy('lastname')->limit(100)->get();

            if ($cards->count() === 1) {
                return redirect()->route('admin.cards.show', $cards->first());
            }
        }

        return view('bonus::admin.cards', ['q' => $q, 'cards' => $cards]);
    }

    public function show(Card $card)
    {
        $wallets = [];
        foreach ($card->getWallets() as $leId => $sum) {
            $wallets[] = ['legal_entity' => $leId === '' ? null : LegalEntity::withTrashed()->find($leId), 'sum' => $sum];
        }

        return view('bonus::admin.card', [
            'card'           => $card,
            'balance'        => $card->getBalance(),
            'wallets'        => $wallets,
            'operations'     => LedgerService::chronological($card->operations()->with(['legal_entity', 'pharmacy', 'user', 'parent']), true)->paginate(50),
            'legal_entities' => LegalEntity::orderBy('name')->get(),
            'ledger_errors'  => LedgerService::checkCard($card->id),
        ]);
    }

    /** Ручная корректировка: + начисление / − списание с причиной. */
    public function adjust(Request $request, Card $card)
    {
        $data = $request->validate([
            'amount'          => ['required', 'numeric', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'legal_entity_id' => ['required', 'integer', 'exists:legal_entities,id'],
            'comment'         => ['required', 'string', 'min:3', 'max:255'],
        ], ['comment.required' => 'Укажите причину корректировки', 'comment.min' => 'Укажите причину корректировки']);

        $amount = round((float) $data['amount'], 2);
        $le = LegalEntity::findOrFail($data['legal_entity_id']);

        $result = DB::transaction(function () use ($card, $amount, $le, $data, $request) {
            $locked = Card::withTrashed()->lockForUpdate()->findOrFail($card->id);
            // баланс может быть отрицательным — ограничений по остатку нет
            return LedgerService::append($locked, $le, null,
                $amount > 0 ? CardOperation::$increase_action : CardOperation::$decreser_action,
                $amount, [
                    'document_date'   => now()->format('Y-m-d'),
                    'document_time'   => now()->format('H:i:s'),
                    'document_number' => 'K' . now()->format('ymdHis'),
                    'document_type'   => self::ADJUST_DOCUMENT_TYPE,
                    'user_id'         => $request->user()->id,
                    'comment'         => $data['comment'],
                ]);
        });

        AdminLog::write('card.adjust', "Карта {$card->short_code}", ['amount' => $amount, 'inn' => $le->inn, 'comment' => $data['comment'], 'operation_id' => $result->id]);
        return back()->with('success', 'Корректировка записана: ' . ($amount > 0 ? '+' : '') . number_format($amount, 2, ',', ' ') . ', остаток ' . number_format($result->card_after, 2, ',', ' '));
    }

    public function block(Request $request, Card $card)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $card->status = Card::$not_avaliable_status;
        $card->deleted_at = now();
        $card->save();
        AdminLog::write('card.block', "Карта {$card->short_code}", ['reason' => $data['reason'] ?? null]);
        return back()->with('success', "Карта {$card->short_code} заблокирована: кассы её больше не принимают");
    }

    public function unblock(Card $card)
    {
        $card->status = Card::$avaliable_status;
        $card->deleted_at = null;
        $card->save();
        AdminLog::write('card.unblock', "Карта {$card->short_code}");
        return back()->with('success', "Карта {$card->short_code} снова действительна");
    }

    /** Замена: весь баланс переносится на новую карту, старая получает статус «заменена». */
    public function replace(Request $request, Card $card)
    {
        $data = $request->validate(['new_short_code' => ['required', 'string', 'min:3', 'max:64']]);
        $code = trim($data['new_short_code']);

        if ($code === $card->short_code) {
            return back()->withErrors(['Укажите номер другой карты']);
        }
        $target = Card::findByShortCode($code);
        if (!$target) {
            return back()->withErrors(["Действующая карта {$code} не найдена — сначала заведите её (через API или загрузку)"]);
        }

        DB::transaction(function () use ($card, $target, $request) {
            $from = Card::withTrashed()->lockForUpdate()->findOrFail($card->id);
            $to   = Card::lockForUpdate()->findOrFail($target->id);
            $document = [
                'document_date'   => now()->format('Y-m-d'),
                'document_time'   => now()->format('H:i:s'),
                'document_number' => 'R' . now()->format('ymdHis'),
                'document_type'   => self::REPLACE_DOCUMENT_TYPE,
                'user_id'         => $request->user()->id,
                'comment'         => "Замена {$from->short_code} → {$to->short_code}",
            ];
            foreach ($from->getWallets() as $leId => $sum) {
                if (abs($sum) < 0.005) {
                    continue;
                }
                $le = $leId === '' ? null : LegalEntity::withTrashed()->find($leId);
                LedgerService::append($to,   $le, null, CardOperation::$move_action,  $sum, $document);
                LedgerService::append($from, $le, null, CardOperation::$move_action, -$sum, $document);
            }
            $from->status = 2; // заменена
            $from->deleted_at = now();
            $from->save();
        });

        AdminLog::write('card.replace', "Карта {$card->short_code}", ['to' => $target->short_code]);
        return redirect()->route('admin.cards.show', $target)->with('success', "Баланс карты {$card->short_code} перенесён на {$target->short_code}; старая карта помечена «заменена»");
    }

    /**
     * Пересчёт остатков по суммам — только admin и только при найденном разрыве.
     * Суммы операций не меняются; card_after / wallet_after перезаписываются.
     */
    public function rebuild(Card $card)
    {
        if (LedgerService::checkCard($card->id) === []) {
            return back()->withErrors(['Цепочка остатков этой карты целая — пересчёт не нужен']);
        }

        $r = DB::transaction(function () use ($card) {
            Card::withTrashed()->lockForUpdate()->findOrFail($card->id);
            return LedgerService::rebuildCard($card->id);
        });

        AdminLog::write('ledger.rebuild', "Карта {$card->short_code}", [
            'changed' => $r['changed'], 'balance_before' => $r['before'], 'balance_after' => $r['after'], 'rows' => $r['rows'],
        ]);
        // убираем карту из списка разрывов последней проверки
        $last = AdminLog::where('action', 'ledger.check')->orderByDesc('id')->first();
        if ($last && !empty($last->details['broken'])) {
            $d = $last->details;
            $d['broken'] = array_values(array_filter($d['broken'], fn ($b) => (int) $b['id'] !== (int) $card->id));
            $d['ok'] = $d['broken'] === [];
            $last->details = $d;
            $last->save();
        }
        return back()->with('success', sprintf('Остатки пересчитаны: изменено строк %d, баланс %s → %s',
            $r['changed'], number_format($r['before'], 2, ',', ' '), number_format($r['after'], 2, ',', ' ')));
    }

    /** Редактирование анкеты клиента из панели. */
    public function profile(Request $request, Card $card)
    {
        $data = $request->validate([
            'firstname'        => ['required', 'string', 'max:255'],
            'name'             => ['nullable', 'string', 'max:255'],
            'lastname'         => ['nullable', 'string', 'max:255'],
            'birthdate'        => ['nullable', 'date'],
            'phone'            => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{10,15}$/'],
            'email'            => ['nullable', 'email', 'max:255'],
            'city'             => ['nullable', 'string', 'max:255'],
            'gender'           => ['required', 'integer', 'between:0,1'],
            'newsletter_agree' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'Телефон — только цифры, можно с +, например +79139465878',
        ]);
        $data['newsletter_agree'] = !empty($data['newsletter_agree']);
        $data['name'] = $data['name'] ?? '';

        $fields = array_keys($data);
        $old = $card->only($fields);
        $card->fill($data);
        $changes = array_keys($card->getDirty());
        $card->save();

        if ($changes) {
            AdminLog::write('card.update', "Карта {$card->short_code}", [
                'old' => array_intersect_key($old, array_flip($changes)),
                'new' => $card->only($changes),
            ]);
        }
        return back()->with('success', $changes ? 'Анкета сохранена' : 'Изменений нет');
    }

    /** Карты с отрицательным балансом. */
    public function negative(Request $request)
    {
        $rows = CardOperation::query()
            ->selectRaw('card_id, ROUND(SUM(amount), 2) AS balance, COUNT(*) AS ops, MAX(document_date) AS last_date')
            ->groupBy('card_id')
            ->havingRaw('SUM(amount) < -0.004')
            ->orderBy('balance')
            ->get();
        $cards = Card::withTrashed()->with('legal_entity')->whereIn('id', $rows->pluck('card_id'))->get()->keyBy('id');
        $list = $rows->map(fn ($r) => ['card' => $cards[$r->card_id] ?? null, 'balance' => (float) $r->balance, 'ops' => (int) $r->ops, 'last_date' => $r->last_date])
            ->filter(fn ($r) => $r['card'] !== null)->values();

        if ($request->boolean('csv')) {
            $out = fopen('php://temp', 'r+');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Карта', 'Фамилия', 'Имя', 'Телефон', 'Юрлицо', 'Баланс', 'Операций', 'Последний документ', 'Статус'], ';');
            foreach ($list as $r) {
                $c = $r['card'];
                fputcsv($out, [$c->short_code, $c->lastname, $c->firstname, $c->phone, $c->legal_entity?->inn, number_format($r['balance'], 2, ',', ''), $r['ops'], $r['last_date'], Card::$statuses[$c->status] ?? $c->status], ';');
            }
            rewind($out);
            return response(stream_get_contents($out), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="cards_negative_' . now()->format('Y-m-d') . '.csv"',
            ]);
        }

        return view('bonus::admin.negative', ['list' => $list, 'total' => $list->sum('balance')]);
    }
}
