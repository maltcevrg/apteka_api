<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * Аптеки: список и смена юрлица (ИНН).
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

    public function storeLegalEntity(Request $request)
    {
        $data = $request->validate([
            'inn'  => ['required', 'regex:/^[0-9]{10,12}$/'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $le = LegalEntity::withTrashed()->firstOrNew(['inn' => $data['inn']]);
        $le->name = $data['name'];
        $le->deleted_at = null;
        $le->save();

        return back()->with('success', "Юрлицо {$le->inn} «{$le->name}» сохранено");
    }
}
