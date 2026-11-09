<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Solt\Bonus\Http\Controllers\BonusController;
use Solt\Bonus\Http\Controllers\CardController;
use Solt\Bonus\Http\Controllers\LegalEntityController;
use Solt\Bonus\Http\Controllers\PharmacyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::group(['prefix' => '/api/v1', 'middleware' => ['client'], 'as' => 'api.'], function(){

    Route::get('/check', [BonusController::class, 'checkApi'])->name('check');

    Route::group(['prefix' => '/bonus', 'as' => 'bonus.'] , function(){
        Route::group(['prefix' => '/card', 'as' => 'card.'] , function(){
            Route::get('/{short_code}', [CardController::class, 'get'])->name('get');
            Route::post('/', [CardController::class, 'store'])->name('store');
            Route::put('/{short_code}', [CardController::class, 'update'])->name('update');
            Route::delete('/{short_code}', [CardController::class, 'delete'])->name('delete');
        });

        Route::group(['prefix' => '/pharmacy', 'as' => 'pharmacy.'] , function(){
            Route::get('/', [PharmacyController::class, 'list'])->name('list');
            Route::get('/{number}', [PharmacyController::class, 'get'])->name('get');
            Route::post('/', [PharmacyController::class, 'store'])->name('store');
            Route::put('/legal-entity/{number}', [PharmacyController::class, 'updateLegalEntity'])->name('update.legal-entity');
            Route::put('/{number}', [PharmacyController::class, 'update'])->name('update');
            Route::delete('/{number}', [PharmacyController::class, 'delete'])->name('delete');
        });

        Route::group(['prefix' => '/legal-entity', 'as' => 'legal-entity.'] , function(){
            Route::get('/', [LegalEntityController::class, 'list'])->name('list');
            Route::get('/{inn}', [LegalEntityController::class, 'get'])->name('get');
            Route::post('/', [LegalEntityController::class, 'store'])->name('store');
            Route::put('/{inn}', [LegalEntityController::class, 'update'])->name('update');
            Route::delete('/{inn}', [LegalEntityController::class, 'delete'])->name('delete');
        });

        Route::group(['prefix' => '/increase', 'as' => 'increase.'] , function(){
            Route::post('/', [CardController::class, 'increase'])->name('store');
            Route::post('/cancel', [CardController::class, 'cancel'])->name('cancel');
            Route::post('/return', [CardController::class, 'increaseReturn'])->name('return');
        });

        Route::group(['prefix' => '/decrease', 'as' => 'decrease.'] , function(){
            Route::post('/', [CardController::class, 'decrease'])->name('store');
            Route::post('/cancel', [CardController::class, 'cancel'])->name('cancel');
            Route::post('/return', [CardController::class, 'decreaseReturn'])->name('return');
        });

        Route::group(['prefix' => '/balance', 'as' => 'balance.'] , function(){
            Route::get('/{short_code}', [CardController::class, 'balance'])->name('get');
        });

        Route::post('/move', [CardController::class, 'move'])->name('move');
        Route::post('/find', [CardController::class, 'find'])->name('find');
    });
});
