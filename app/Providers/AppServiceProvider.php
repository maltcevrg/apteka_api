<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Only the token endpoint is used (client credentials grant). It is registered in RouteServiceProvider.
        // Passport 13 ships no pages, so its /oauth/authorize and /oauth/device routes would fail without a web UI.
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
