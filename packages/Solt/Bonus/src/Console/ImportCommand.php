<?php

namespace Solt\Bonus\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;
use Solt\Bonus\Services\LedgerService;

/**
 * Загрузка справочников и карт с начальными остатками из CSV (database/import).
 *
 * Файлы (UTF-8, разделитель «;», первая строка — заголовок):
 *   legal_entities.csv   inn;name
 *   pharmacies.csv       number;name;inn
 *   cards_<ИНН>.csv      short_code;firstname;name;lastname;birthdate;phone;city;gender;newsletter_agree;status;guid;balance
 *
 * Остаток карты (balance) записывается первой операцией журнала — начислением
 * от юрлица <ИНН> с типом документа «Ввод остатков». Повторный запуск безопасен:
 * справочники обновляются, карты — обновляются, остаток вводится только картам
 * без единой операции.
 */
class ImportCommand extends Command
{
    protected $signature = 'bonus:import
        {dir? : Папка с CSV. По умолчанию database/import}
        {--date= : Дата документа ввода остатков (ГГГГ-ММ-ДД). По умолчанию сегодня}';

    protected $description = 'Загрузить юрлица, аптеки и карты с начальными остатками из CSV';

    public const OPENING_DOCUMENT_TYPE = 'Ввод остатков';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir') ?: base_path('database/import'), '/');
        if (!is_dir($dir)) {
            $this->error("Папка не найдена: {$dir}");
            return self::FAILURE;
        }
        $date = $this->option('date') ? Carbon::parse($this->option('date'))->format('Y-m-d') : now()->format('Y-m-d');

        $stats = ['legal_entities' => 0, 'pharmacies' => 0, 'cards' => 0, 'openings' => 0, 'skipped' => []];

        DB::transaction(function () use ($dir, $date, &$stats) {
            $this->importLegalEntities("{$dir}/legal_entities.csv", $stats);
            $this->importPharmacies("{$dir}/pharmacies.csv", $stats);
            foreach (glob("{$dir}/cards_*.csv") as $file) {
                if (preg_match('/cards_(\d+)\.csv$/', $file, $m)) {
                    $this->importCards($file, $m[1], $date, $stats);
                }
            }
        });

        $this->line("Юрлиц: {$stats['legal_entities']}, аптек: {$stats['pharmacies']}, карт: {$stats['cards']}, начальных остатков введено: {$stats['openings']}");
        foreach ($stats['skipped'] as $msg) {
            $this->warn($msg);
        }
        $this->info('Импорт завершён.');
        return self::SUCCESS;
    }

    private function rows(string $file): \Generator
    {
        if (!is_file($file)) {
            $this->warn("Нет файла {$file} — пропущен");
            return;
        }
        $h = fopen($file, 'r');
        $header = null;
        while (($line = fgets($h)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $cols = array_map('trim', explode(';', $line));
            if ($header === null) {
                $header = array_map(fn ($c) => trim($c, "\xEF\xBB\xBF \t"), $cols);
                continue;
            }
            $cols = array_pad(array_slice($cols, 0, count($header)), count($header), '');
            yield array_combine($header, $cols);
        }
        fclose($h);
    }

    private function importLegalEntities(string $file, array &$stats): void
    {
        foreach ($this->rows($file) as $r) {
            $inn = $this->cleanInn($r['inn'] ?? '');
            if ($inn === '') {
                continue;
            }
            $le = LegalEntity::withTrashed()->firstOrNew(['inn' => $inn]);
            $le->name = $r['name'] ?? $inn;
            $le->deleted_at = null;
            $le->save();
            $stats['legal_entities']++;
        }
    }

    private function importPharmacies(string $file, array &$stats): void
    {
        foreach ($this->rows($file) as $r) {
            $inn = $this->cleanInn($r['inn'] ?? '');
            $le = LegalEntity::where('inn', $inn)->first();
            if (!$le) {
                $stats['skipped'][] = "Аптека {$r['number']}: юрлицо с ИНН {$inn} не найдено — пропущена";
                continue;
            }
            $ph = Pharmacy::withTrashed()->firstOrNew(['number' => (int) $r['number']]);
            $ph->name = $r['name'] ?? '';
            $ph->legal_entity()->associate($le);
            $ph->deleted_at = null;
            $ph->save();
            $stats['pharmacies']++;
        }
    }

    private function importCards(string $file, string $inn, string $date, array &$stats): void
    {
        $le = LegalEntity::where('inn', $inn)->first();
        if (!$le) {
            $stats['skipped'][] = basename($file) . ": юрлицо с ИНН {$inn} не найдено — файл пропущен";
            return;
        }
        $n = 0;
        foreach ($this->rows($file) as $r) {
            $code = $r['short_code'] ?? '';
            if ($code === '') {
                continue;
            }
            $card = Card::withTrashed()->firstOrNew(['short_code' => $code]);
            $card->firstname        = $r['firstname'] ?? '';
            $card->name             = $r['name'] ?? '';
            $card->lastname         = ($r['lastname'] ?? '') !== '' ? $r['lastname'] : null;
            $card->birthdate        = ($r['birthdate'] ?? '') !== '' ? Carbon::parse($r['birthdate'])->format('Y-m-d') : null;
            $card->phone            = ($r['phone'] ?? '') !== '' ? $r['phone'] : null;
            $card->city             = ($r['city'] ?? '') !== '' ? $r['city'] : null;
            $card->gender           = (int) ($r['gender'] ?? 0);
            $card->newsletter_agree = (int) ($r['newsletter_agree'] ?? 0);
            $card->status           = (int) ($r['status'] ?? 0);
            $card->guid             = ($r['guid'] ?? '') !== '' ? $r['guid'] : null;
            $card->deleted_at       = null;
            $card->save();
            $stats['cards']++;
            $n++;

            $balance = (float) str_replace(',', '.', $r['balance'] ?? '0');
            if (abs($balance) >= 0.005 && !CardOperation::where('card_id', $card->id)->exists()) {
                LedgerService::append($card, $le, null, CardOperation::$increase_action, $balance, [
                    'document_date'   => $date,
                    'document_number' => 'OPEN-' . substr($code, -7),
                    'document_type'   => self::OPENING_DOCUMENT_TYPE,
                ]);
                $stats['openings']++;
            }
        }
        $this->line(basename($file) . ": {$n} карт");
    }

    private function cleanInn(string $inn): string
    {
        // «5445108738\544601001» — ИНН\КПП, нужен только ИНН
        return trim(explode('\\', str_replace('/', '\\', $inn))[0]);
    }
}
