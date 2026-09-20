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
});
