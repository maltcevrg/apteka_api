<?php

namespace Solt\Bonus\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * Загрузка справочников и карт с начальными остатками из CSV.
 *
 * Принимает как «наши» файлы (database/import, UTF-8, английские заголовки),
 * так и исходные выгрузки 1С (cp1251, русские заголовки, хвостовой «;»):
 *   юрлица   inn;name            | ИНН;Наименование
 *   аптеки   number;name;inn     | НомерАптеки;Наименование;ИНН
 *   карты    short_code;firstname;name;lastname;birthdate;phone;city;gender;newsletter_agree;status;guid;balance
 *
 * Остаток карты записывается первой операцией журнала — начислением от юрлица
 * с типом документа «Ввод остатков». Повторная загрузка безопасна: справочники
 * и карты обновляются, остаток вводится только картам без единой операции.
 */
class ImportService
{
    public const OPENING_DOCUMENT_TYPE = 'Ввод остатков';

    /** Соответствие русских заголовков 1С нашим. */
    private const HEADERS = [
        'инн' => 'inn', 'наименование' => 'name', 'номераптеки' => 'number', 'номер' => 'number',
    ];

    /** @var array{legal_entities:int, pharmacies:int, cards:int, openings:int, skipped:string[], log:string[]} */
    public array $stats;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->stats = ['legal_entities' => 0, 'pharmacies' => 0, 'cards' => 0, 'openings' => 0, 'skipped' => [], 'log' => []];
    }

    /**
     * Загрузить папку целиком (legal_entities.csv, pharmacies.csv, cards_<ИНН>.csv).
     */
    public function importDir(string $dir, ?string $date = null): array
    {
        $dir = rtrim($dir, '/');
        $cards = [];
        foreach (glob("{$dir}/cards_*.csv") ?: [] as $file) {
            if (preg_match('/cards_(\d+)\.csv$/', $file, $m)) {
                $cards[$m[1]] = $file;
            }
        }
        return $this->import(
            is_file("{$dir}/legal_entities.csv") ? "{$dir}/legal_entities.csv" : null,
            is_file("{$dir}/pharmacies.csv") ? "{$dir}/pharmacies.csv" : null,
            $cards,
            $date
        );
    }

    /**
     * Загрузить набор файлов в одной транзакции.
     *
     * @param array<string,string> $cardFiles  ИНН => путь к файлу карт
     */
    public function import(?string $legalEntitiesFile, ?string $pharmaciesFile, array $cardFiles, ?string $date = null): array
    {
        $date = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');

        DB::transaction(function () use ($legalEntitiesFile, $pharmaciesFile, $cardFiles, $date) {
            if ($legalEntitiesFile) {
                $this->importLegalEntities($legalEntitiesFile);
            }
            if ($pharmaciesFile) {
                $this->importPharmacies($pharmaciesFile);
            }
            foreach ($cardFiles as $inn => $file) {
                $this->importCards($file, (string) $inn, $date);
            }
        });

        return $this->stats;
    }

    /**
     * ИНН из имени файла: cards_544505317704.csv, Бонусы544505317704.csv.
     */
    public static function innFromFilename(string $name): ?string
    {
        return preg_match('/(\d{10,12})\D*\.csv$/iu', $name, $m) ? $m[1] : null;
    }

    /**
     * Строки CSV как ассоциативные массивы. Кодировка определяется автоматически
     * (UTF-8 или cp1251), заголовки приводятся к нашим именам.
     */
    public function rows(string $file): \Generator
    {
        $h = fopen($file, 'r');
        $header = null;
        while (($line = fgets($h)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            if (!mb_check_encoding($line, 'UTF-8')) {
                $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1251');
            }
            $cols = array_map('trim', explode(';', $line));
            if ($header === null) {
                $header = [];
                foreach ($cols as $c) {
                    $c = trim($c, "\xEF\xBB\xBF \t");
                    $key = mb_strtolower($c);
                    $header[] = self::HEADERS[$key] ?? $key;
                }
                continue;
            }
            $cols = array_pad(array_slice($cols, 0, count($header)), count($header), '');
            yield array_combine($header, $cols);
        }
        fclose($h);
    }

    private function importLegalEntities(string $file): void
    {
        foreach ($this->rows($file) as $r) {
            $inn = self::cleanInn($r['inn'] ?? '');
            if ($inn === '') {
                continue;
            }
            $le = LegalEntity::withTrashed()->firstOrNew(['inn' => $inn]);
            $le->name = ($r['name'] ?? '') !== '' ? $r['name'] : $inn;
            $le->deleted_at = null;
            $le->save();
            $this->stats['legal_entities']++;
        }
    }

    private function importPharmacies(string $file): void
    {
        foreach ($this->rows($file) as $r) {
            if (($r['number'] ?? '') === '') {
                continue;
            }
            $inn = self::cleanInn($r['inn'] ?? '');
            $le = LegalEntity::where('inn', $inn)->first();
            if (!$le) {
                $this->stats['skipped'][] = "Аптека {$r['number']}: юрлицо с ИНН {$inn} не найдено — пропущена";
                continue;
            }
            $ph = Pharmacy::withTrashed()->firstOrNew(['number' => (int) $r['number']]);
            $ph->name = $r['name'] ?? '';
            $ph->legal_entity()->associate($le);
            $ph->deleted_at = null;
            $ph->save();
            $this->stats['pharmacies']++;
        }
    }

    private function importCards(string $file, string $inn, string $date): void
    {
        $le = LegalEntity::where('inn', $inn)->first();
        if (!$le) {
            $this->stats['skipped'][] = basename($file) . ": юрлицо с ИНН {$inn} не найдено — файл пропущен";
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
            if ($card->legal_entity_id === null) {
                $card->legal_entity_id = $le->id;
            }
            $card->save();
            $this->stats['cards']++;
            $n++;

            $balance = (float) str_replace(',', '.', $r['balance'] ?? '0');
            if (abs($balance) >= 0.005 && !CardOperation::where('card_id', $card->id)->exists()) {
                LedgerService::append($card, $le, null, CardOperation::$increase_action, $balance, [
                    'document_date'   => $date,
                    'document_number' => 'OPEN-' . substr($code, -7),
                    'document_type'   => self::OPENING_DOCUMENT_TYPE,
                ]);
                $this->stats['openings']++;
            }
        }
        $this->stats['log'][] = basename($file) . " (ИНН {$inn}): {$n} карт";
    }

    public static function cleanInn(string $inn): string
    {
        // «5445108738\544601001» — ИНН\КПП, нужен только ИНН
        return trim(explode('\\', str_replace('/', '\\', $inn))[0]);
    }
}
