<?php
namespace Solt\Bonus\Providers;

use Illuminate\Support\ServiceProvider;

class BonusServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Без проверки runningInConsole(): команды должны быть доступны
        // и при запуске artisan из веб-скрипта (public/obnovlenie.php).
        $this->commands([
            \Solt\Bonus\Console\LedgerCheckCommand::class,
            \Solt\Bonus\Console\ImportCommand::class,
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

    }
}
