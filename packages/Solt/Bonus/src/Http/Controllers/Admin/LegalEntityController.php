<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Solt\Bonus\Models\AdminLog;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * Юрлица: список, добавление, удаление.
 */
class LegalEntityController extends Controller
{
    public function index()
    {
        $pharmacyCounts = Pharmacy::selectRaw('legal_entity_id, COUNT(*) AS n')->groupBy('legal_entity_id')->pluck('n', 'legal_entity_id');
        $cardCounts     = Card::selectRaw('legal_entity_id, COUNT(*) AS n')->groupBy('legal_entity_id')->pluck('n', 'legal_entity_id');

        return view('bonus::admin.legal_entities', [
            'legal_entities'  => LegalEntity::orderBy('name')->get(),
            'pharmacy_counts' => $pharmacyCounts,
            'card_counts'     => $cardCounts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'inn'  => ['required', 'regex:/^[0-9]{10,12}$/'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if (LegalEntity::where('inn', $data['inn'])->exists()) {
            return back()->withInput()->withErrors(["Юрлицо с ИНН {$data['inn']} уже есть"]);
        }

        $le = LegalEntity::withTrashed()->firstOrNew(['inn' => $data['inn']]);
        $le->name = $data['name'];
        $le->deleted_at = null;
        $le->save();

        AdminLog::write('legal_entity.create', "Юрлицо {$le->inn}", ['name' => $le->name]);
        return back()->with('success', "Юрлицо {$le->inn} «{$le->name}» добавлено");
    }

    public function update(Request $request, LegalEntity $legal_entity)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $old = $legal_entity->name;
        $legal_entity->name = $data['name'];
        $legal_entity->save();

        AdminLog::write('legal_entity.update', "Юрлицо {$legal_entity->inn}", ['old' => $old, 'new' => $legal_entity->name]);
        return back()->with('success', "Юрлицо {$legal_entity->inn}: название сохранено");
    }

    public function destroy(LegalEntity $legal_entity)
    {
        $n = Pharmacy::where('legal_entity_id', $legal_entity->id)->count();
        if ($n > 0) {
            return back()->withErrors(["Нельзя удалить {$legal_entity->inn} «{$legal_entity->name}»: за ним числятся аптеки ({$n}). Сначала переведите их на другое юрлицо или удалите."]);
        }

        $legal_entity->delete();
        AdminLog::write('legal_entity.delete', "Юрлицо {$legal_entity->inn}", ['name' => $legal_entity->name]);
        return back()->with('success', "Юрлицо {$legal_entity->inn} «{$legal_entity->name}» удалено. Операции и карты в журнале сохранены.");
    }
}
