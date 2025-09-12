<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;

Route::get('logout', 'App\Http\Controllers\Auth\LoginController@logout');
Route::post('login', 'App\Http\Controllers\Auth\LoginController@login');
Route::get('consoList', 'App\Http\Controllers\ConsortiumController@index')->name('consoList');
Route::post('forgotPass','App\Http\Controllers\Auth\ForgotPasswordController@submitForgotForm')
        ->name('forgotPass');
Route::post('resetPass/','App\Http\Controllers\Auth\ForgotPasswordController@submitResetForm')
        ->name('resetPass');

Route::middleware('ccplusAuth')->group( function () {
    Route::get('/user', function (Request $request) {
       return $request->user();
    });
    Route::get('getCreds', 'App\Http\Controllers\SushiSettingController@index')->name('getCreds');
    Route::get('getInsts', 'App\Http\Controllers\InstitutionController@index')->name('getInsts');
    Route::get('getInstTypes', 'App\Http\Controllers\InstitutionTypeController@index')->name('getInstTypes');
    Route::get('getInstGroups', 'App\Http\Controllers\InstitutionGroupController@index')->name('getInstGroups');
    Route::get('getPlatforms', 'App\Http\Controllers\GlobalProviderController@index')->name('getPlatforms');
    Route::get('getUsers', 'App\Http\Controllers\UserController@index')->name('getUsers');
});
