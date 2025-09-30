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
    Route::get('getCreds', 'App\Http\Controllers\CredentialController@index')->name('getCreds');
    Route::get('getInsts/{role}', 'App\Http\Controllers\InstitutionController@index')->name('getInsts');
    Route::get('getInstTypes', 'App\Http\Controllers\InstitutionTypeController@index')->name('getInstTypes');
    Route::get('getInstGroups', 'App\Http\Controllers\InstitutionGroupController@index')->name('getInstGroups');
    Route::get('getPlatforms/{role}', 'App\Http\Controllers\GlobalProviderController@index')->name('getPlatforms');
    Route::get('getUsers', 'App\Http\Controllers\UserController@index')->name('getUsers');
    Route::get('getSettings/{type}', 'App\Http\Controllers\GlobalSettingController@index')->name('getSettings');
    Route::get('getHarvests', 'App\Http\Controllers\HarvestLogController@index')->name('getHarvests');
    Route::get('getJobs', 'App\Http\Controllers\HarvestLogController@harvestQueue')->name('getJobs');
    Route::get('getManualOptions', 'App\Http\Controllers\HarvestLogController@create')->name('getManualOptions');
    Route::get('getSavedReports', 'App\Http\Controllers\SavedReportController@index')->name('getSavedReports');
//
    Route::post('setSettings', 'App\Http\Controllers\GlobalSettingController@store')->name('setSettings');
    Route::post('storeHarvests', 'App\Http\Controllers\HarvestLogController@store')->name('storeHarvests');
});
