<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Solt\Bonus\Models\AdminLog;
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

        AdminLog::write('pharmacy.create', "Аптека {$pharmacy->number}", ['name' => $pharmacy->name, 'inn' => $pharmacy->legal_entity->inn]);
        return back()->with('success', "Аптека {$pharmacy->number} «{$pharmacy->name}» добавлена");
    }

    public function update(Request $request, Pharmacy $pharmacy)
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'between:0,99'],
            'name'   => ['required', 'string', 'max:255'],
        ]);
        if ((int) $data['number'] !== $pharmacy->number && Pharmacy::where('number', $data['number'])->exists()) {
            return back()->withErrors(["Номер {$data['number']} уже занят другой аптекой"]);
        }
        $old = ['number' => $pharmacy->number, 'name' => $pharmacy->name];
        $pharmacy->number = (int) $data['number'];
        $pharmacy->name = $data['name'];
        $pharmacy->save();

        AdminLog::write('pharmacy.update', "Аптека {$pharmacy->number}", ['old' => $old, 'new' => ['number' => $pharmacy->number, 'name' => $pharmacy->name]]);
        return back()->with('success', "Аптека {$pharmacy->number} «{$pharmacy->name}» сохранена");
    }

    public function destroy(Pharmacy $pharmacy)
    {
        $pharmacy->delete();
        AdminLog::write('pharmacy.delete', "Аптека {$pharmacy->number}", ['name' => $pharmacy->name]);
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

        AdminLog::write('pharmacy.legal_entity', "Аптека {$pharmacy->number}", ['from' => $old, 'to' => $le->inn]);
        return back()->with('success', "Аптека {$pharmacy->number} «{$pharmacy->name}»: ИНН {$old} → {$le->inn} ({$le->name})");
    }
}
