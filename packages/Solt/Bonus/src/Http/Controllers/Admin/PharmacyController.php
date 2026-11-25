<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * Аптеки: список, добавление, удаление, смена юрлица (ИНН).
 */
class PharmacyController extends Controller
{
    public function index()
    {
        return view('bonus::admin.pharmacies', [
            'pharmacies'     => Pharmacy::with('legal_entity')->orderBy('number')->get(),
            'legal_entities' => LegalEntity::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'number'          => ['required', 'integer', 'between:0,99'],
            'name'            => ['required', 'string', 'max:255'],
            'legal_entity_id' => ['required', 'integer', 'exists:legal_entities,id,deleted_at,NULL'],
        ]);

        if (Pharmacy::where('number', $data['number'])->exists()) {
            return back()->withInput()->withErrors(["Аптека с номером {$data['number']} уже есть"]);
        }

        // Если такая аптека была удалена раньше — восстанавливаем ту же запись
        $pharmacy = Pharmacy::withTrashed()->firstOrNew(['number' => $data['number']]);
        $pharmacy->name = $data['name'];
        $pharmacy->legal_entity()->associate(LegalEntity::findOrFail($data['legal_entity_id']));
        $pharmacy->deleted_at = null;
        $pharmacy->save();

        return back()->with('success', "Аптека {$pharmacy->number} «{$pharmacy->name}» добавлена");
    }

    public function destroy(Pharmacy $pharmacy)
    {
        $pharmacy->delete();
        return back()->with('success', "Аптека {$pharmacy->number} «{$pharmacy->name}» удалена. Её чеки в журнале сохранены.");
    }

    public function updateLegalEntity(Request $request, Pharmacy $pharmacy)
    {
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer', 'exists:legal_entities,id,deleted_at,NULL'],
        ]);

        $le = LegalEntity::findOrFail($data['legal_entity_id']);
        if ($pharmacy->legal_entity_id === $le->id) {
            return back()->with('info', "Аптека {$pharmacy->number}: юрлицо не изменилось");
        }

        $old = $pharmacy->legal_entity?->inn;
        $pharmacy->legal_entity()->associate($le);
        $pharmacy->save();

        return back()->with('success', "Аптека {$pharmacy->number} «{$pharmacy->name}»: ИНН {$old} → {$le->inn} ({$le->name})");
    }
}
