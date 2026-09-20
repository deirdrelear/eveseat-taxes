<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'DeirdreLear\\Seat\\Taxes\\Http\\Controllers',
    'prefix' => 'taxes',
    'middleware' => ['web', 'auth', 'locale'],
], function () {
    Route::get('/', [
        'as' => 'taxes.dashboard',
        'uses' => 'DashboardController@index',
        'middleware' => 'can:taxes.view',
    ]);

    Route::get('/diagnostics', [
        'as' => 'taxes.diagnostics',
        'uses' => 'DiagnosticsController@index',
        'middleware' => 'can:taxes.view',
    ]);

    Route::get('/rules', [
        'as' => 'taxes.rules',
        'uses' => 'RuleSetController@index',
        'middleware' => 'can:taxes.manage',
    ]);

    Route::post('/rules', [
        'as' => 'taxes.rules.store',
        'uses' => 'RuleSetController@store',
        'middleware' => 'can:taxes.manage',
    ]);

    Route::post('/rules/{ruleSet}/close', [
        'as' => 'taxes.rules.close',
        'uses' => 'RuleSetController@close',
        'middleware' => 'can:taxes.manage',
    ]);

    Route::get('/dry-run', [
        'as' => 'taxes.dry-run',
        'uses' => 'DryRunController@index',
        'middleware' => 'can:taxes.recalculate',
    ]);
});
