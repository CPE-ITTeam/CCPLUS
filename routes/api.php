<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('logout', 'App\Http\Controllers\Auth\LoginController@logout');
Route::post('login', 'App\Http\Controllers\Auth\LoginController@login');
Route::middleware('auth:sanctum')->group( function () {
    Route::get('/user', function (Request $request) {
       return $request->user();
    });
});
//
Route::get('consoList', 'App\Http\Controllers\ConsortiumController@index')->name('consoList');
