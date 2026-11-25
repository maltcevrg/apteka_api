<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Solt\Bonus\Services\ImportService;

/**
 * Загрузка CSV из 1С: юрлица, аптеки, карты с остатками.
 */
class ImportController extends Controller
{
    public function index()
    {
        return view('bonus::admin.import');
    }

    public function store(Request $request, ImportService $import)
    {
        $request->validate([
            'legal_entities' => ['nullable', 'file', 'max:10240'],
            'pharmacies'     => ['nullable', 'file', 'max:10240'],
            'cards'          => ['nullable', 'array'],
            'cards.*'        => ['file', 'max:51200'],
            'date'           => ['nullable', 'date'],
        ]);

        $cardFiles = [];
        $names = [];
        $errors = [];
        foreach ($request->file('cards', []) as $file) {
            $inn = $request->input('inn') ?: ImportService::innFromFilename($file->getClientOriginalName());
            if (!$inn) {
                $errors[] = "{$file->getClientOriginalName()}: не удалось определить ИНН из имени файла — укажите его в поле «ИНН для файлов карт»";
                continue;
            }
            $cardFiles[$inn] = $file->getRealPath();
            $names[$file->getRealPath()] = $file->getClientOriginalName();
        }

        if (!$request->hasFile('legal_entities') && !$request->hasFile('pharmacies') && !$cardFiles) {
            return back()->withErrors($errors ?: ['Не выбран ни один файл']);
        }

        try {
            $stats = $import->import(
                $request->hasFile('legal_entities') ? $request->file('legal_entities')->getRealPath() : null,
                $request->hasFile('pharmacies') ? $request->file('pharmacies')->getRealPath() : null,
                $cardFiles,
                $request->input('date') ?: null,
                $names
            );
        } catch (\Throwable $e) {
            return back()->withErrors(array_merge($errors, ['Ошибка загрузки, ничего не записано: ' . $e->getMessage()]));
        }

        return view('bonus::admin.import', ['stats' => $stats, 'file_errors' => $errors]);
    }
}
