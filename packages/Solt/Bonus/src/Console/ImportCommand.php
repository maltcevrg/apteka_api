<?php

namespace Solt\Bonus\Console;

use Illuminate\Console\Command;
use Solt\Bonus\Services\ImportService;

/**
 * Загрузка справочников и карт с начальными остатками из CSV (database/import).
 * Логика — в ImportService (та же, что в админ-панели).
 */
class ImportCommand extends Command
{
    protected $signature = 'bonus:import
        {dir? : Папка с CSV. По умолчанию database/import}
        {--date= : Дата документа ввода остатков (ГГГГ-ММ-ДД). По умолчанию сегодня}';

    protected $description = 'Загрузить юрлица, аптеки и карты с начальными остатками из CSV';

    public const OPENING_DOCUMENT_TYPE = ImportService::OPENING_DOCUMENT_TYPE;

    public function handle(ImportService $import): int
    {
        $dir = rtrim($this->argument('dir') ?: base_path('database/import'), '/');
        if (!is_dir($dir)) {
            $this->error("Папка не найдена: {$dir}");
            return self::FAILURE;
        }

        $stats = $import->importDir($dir, $this->option('date'));

        foreach ($stats['log'] as $msg) {
            $this->line($msg);
        }
        $this->line("Юрлиц: {$stats['legal_entities']}, аптек: {$stats['pharmacies']}, карт: {$stats['cards']}, начальных остатков введено: {$stats['openings']}");
        foreach ($stats['skipped'] as $msg) {
            $this->warn($msg);
        }
        $this->info('Импорт завершён.');
        return self::SUCCESS;
    }
}
