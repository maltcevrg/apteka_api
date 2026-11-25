<?php

use Illuminate\Support\Facades\Route;
use Solt\Bonus\Http\Controllers\Admin\AuthController;
use Solt\Bonus\Http\Controllers\Admin\DashboardController;
use Solt\Bonus\Http\Controllers\Admin\ImportController;
use Solt\Bonus\Http\Controllers\Admin\LegalEntityController;
use Solt\Bonus\Http\Controllers\Admin\PharmacyController;
use Solt\Bonus\Http\Controllers\Admin\ReportController;
use Solt\Bonus\Http\Middleware\AdminAuth;

/*
 * Админ-панель: /admin. Сессии и CSRF — группа middleware «web»,
 * вход по логину и паролю из таблицы users.
 */
Route::group(['prefix' => '/admin', 'middleware' => ['web'], 'as' => 'admin.'], function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::group(['middleware' => [AdminAuth::class]], function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/pharmacies', [PharmacyController::class, 'index'])->name('pharmacies');
        Route::post('/pharmacies', [PharmacyController::class, 'store'])->name('pharmacies.store');
        Route::post('/pharmacies/{pharmacy}/legal-entity', [PharmacyController::class, 'updateLegalEntity'])->name('pharmacies.legal-entity');
        Route::delete('/pharmacies/{pharmacy}', [PharmacyController::class, 'destroy'])->name('pharmacies.destroy');

        Route::get('/legal-entities', [LegalEntityController::class, 'index'])->name('legal-entities');
        Route::post('/legal-entities', [LegalEntityController::class, 'store'])->name('legal-entities.store');
        Route::delete('/legal-entities/{legal_entity}', [LegalEntityController::class, 'destroy'])->name('legal-entities.destroy');

        Route::get('/import', [ImportController::class, 'index'])->name('import');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    });
});
