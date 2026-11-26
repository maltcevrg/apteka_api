<?php

use Illuminate\Support\Facades\Route;
use Solt\Bonus\Http\Controllers\Admin\AuthController;
use Solt\Bonus\Http\Controllers\Admin\CardController;
use Solt\Bonus\Http\Controllers\Admin\ClientController;
use Solt\Bonus\Http\Controllers\Admin\DashboardController;
use Solt\Bonus\Http\Controllers\Admin\ImportController;
use Solt\Bonus\Http\Controllers\Admin\LegalEntityController;
use Solt\Bonus\Http\Controllers\Admin\LogController;
use Solt\Bonus\Http\Controllers\Admin\OperationController;
use Solt\Bonus\Http\Controllers\Admin\PharmacyController;
use Solt\Bonus\Http\Controllers\Admin\ReportController;
use Solt\Bonus\Http\Controllers\Admin\UserController;
use Solt\Bonus\Http\Middleware\AdminAuth;
use Solt\Bonus\Http\Middleware\AdminRole;

/*
 * Админ-панель: /admin. Сессии и CSRF — группа middleware «web»,
 * вход по логину и паролю из таблицы users.
 *
 * Роли: admin — всё; editor — изменение данных; user — только просмотр и отчёты.
 */
Route::group(['prefix' => '/admin', 'middleware' => ['web'], 'as' => 'admin.'], function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
    Route::get('/password/setup', [AuthController::class, 'showSetup'])->name('password.setup');
    Route::post('/password/setup', [AuthController::class, 'setup'])->middleware('throttle:30,1')->name('password.setup.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::group(['middleware' => [AdminAuth::class]], function () {
        // Просмотр — всем ролям
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/pharmacies', [PharmacyController::class, 'index'])->name('pharmacies');
        Route::get('/legal-entities', [LegalEntityController::class, 'index'])->name('legal-entities');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/cards', [CardController::class, 'index'])->name('cards');
        Route::get('/cards/{card}', [CardController::class, 'show'])->withTrashed()->name('cards.show');
        Route::get('/operations', [OperationController::class, 'index'])->name('operations');
        Route::get('/password', [AuthController::class, 'showChangePassword'])->name('password.change');
        Route::post('/password', [AuthController::class, 'changePassword'])->name('password.change.post');

        // Изменение данных — admin и editor
        Route::group(['middleware' => [AdminRole::class . ':admin,editor']], function () {
            Route::post('/pharmacies', [PharmacyController::class, 'store'])->name('pharmacies.store');
            Route::post('/pharmacies/{pharmacy}', [PharmacyController::class, 'update'])->name('pharmacies.update');
            Route::post('/pharmacies/{pharmacy}/legal-entity', [PharmacyController::class, 'updateLegalEntity'])->name('pharmacies.legal-entity');
            Route::delete('/pharmacies/{pharmacy}', [PharmacyController::class, 'destroy'])->name('pharmacies.destroy');

            Route::post('/legal-entities', [LegalEntityController::class, 'store'])->name('legal-entities.store');
            Route::post('/legal-entities/{legal_entity}', [LegalEntityController::class, 'update'])->name('legal-entities.update');
            Route::delete('/legal-entities/{legal_entity}', [LegalEntityController::class, 'destroy'])->name('legal-entities.destroy');

            Route::get('/import', [ImportController::class, 'index'])->name('import');
            Route::post('/import', [ImportController::class, 'store'])->name('import.store');

            Route::post('/cards/{card}/adjust', [CardController::class, 'adjust'])->withTrashed()->name('cards.adjust');
            Route::post('/cards/{card}/block', [CardController::class, 'block'])->name('cards.block');
            Route::post('/cards/{card}/unblock', [CardController::class, 'unblock'])->withTrashed()->name('cards.unblock');
            Route::post('/cards/{card}/replace', [CardController::class, 'replace'])->withTrashed()->name('cards.replace');
            Route::post('/operations/check', [OperationController::class, 'check'])->name('operations.check');
        });

        // Пользователи — только admin
        Route::group(['middleware' => [AdminRole::class . ':admin']], function () {
            Route::get('/users', [UserController::class, 'index'])->name('users');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::post('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            Route::get('/clients', [ClientController::class, 'index'])->name('clients');
            Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
            Route::post('/clients/{client}/secret', [ClientController::class, 'regenerate'])->name('clients.secret');
            Route::post('/clients/{client}/revoke', [ClientController::class, 'revoke'])->name('clients.revoke');

            Route::get('/log', [LogController::class, 'index'])->name('log');
        });
    });
});
