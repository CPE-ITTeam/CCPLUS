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

// Routes requiring authentication
Route::middleware('ccplusAuth')->group( function () {
    Route::post('/updateSession','App\Http\Controllers\SessionController@update')->name('updateSession');
    Route::get('/user', function (Request $request) {
       return $request->user();
    });
    // Routes grouped by prefix
    Route::prefix('consortia')->group(function () {
        Route::get('/get', 'App\Http\Controllers\ConsortiumController@index')->name('consortia.index');
        Route::post('/store', 'App\Http\Controllers\ConsortiumController@store')->name('consortia.store');
        Route::patch('/update/{consortium}', 'App\Http\Controllers\ConsortiumController@update')->name('consortia.update');
    });
    Route::prefix('credentials')->group(function () {
        Route::get('/get', 'App\Http\Controllers\CredentialController@index')->name('credentials.index');
        Route::post('/store', 'App\Http\Controllers\CredentialController@store')->name('credentials.store');
        Route::patch('/update/{credential}', 'App\Http\Controllers\CredentialController@update')->name('credentials.update');
        Route::delete('/delete/{credential}', 'App\Http\Controllers\CredentialController@destroy')->name('credentials.destroy');
        Route::post('/bulk', 'App\Http\Controllers\CredentialController@bulk')->name('credentials.bulk');
        Route::post('/unset', 'App\Http\Controllers\CredentialController@unset')->name('credentials.unset');
        Route::post('/test', 'App\Http\Controllers\CredentialController@test')->name('credentials.test');
        Route::post('/import', 'App\Http\Controllers\CredentialController@import')->name('credentials.import');
    });
    Route::prefix('audit')->group(function () {
        Route::get('/get', 'App\Http\Controllers\CredentialController@audit')->name('audit.index');
        Route::patch('/update/{credential}', 'App\Http\Controllers\CredentialController@setvalidated')->name('audit.update');
        Route::post('/bulk', 'App\Http\Controllers\CredentialController@bulk')->name('audit.bulk');
    });
    Route::prefix('connections')->group(function () {
        Route::get('/get', 'App\Http\Controllers\ConnectionController@index')->name('connections.index');
        Route::post('/store', 'App\Http\Controllers\ConnectionController@store')->name('connections.store');
        Route::patch('/update/{connection}', 'App\Http\Controllers\ConnectionController@update')->name('connections.update');
        Route::delete('/delete/{connection}', 'App\Http\Controllers\ConnectionController@destroy')->name('connections.destroy');
        Route::post('/import', 'App\Http\Controllers\ConnectionController@import')->name('connections.import');
        Route::post('/access', 'App\Http\Controllers\ConnectionController@access')->name('connections.reportAccess');
    });
    Route::prefix('institutions')->group(function () {
        Route::get('/get/{role}', 'App\Http\Controllers\InstitutionController@index')->name('institutions.index');
        Route::post('/store', 'App\Http\Controllers\InstitutionController@store')->name('institutions.store');
        Route::patch('/update/{institution}', 'App\Http\Controllers\InstitutionController@update')->name('institutions.update');
        Route::delete('/delete/{institution}', 'App\Http\Controllers\InstitutionController@destroy')->name('institutions.destroy');
        Route::post('/bulk', 'App\Http\Controllers\InstitutionController@bulk')->name('institutions.bulk');
        Route::post('/import', 'App\Http\Controllers\InstitutionController@import')->name('institutions.import');
    });
    Route::prefix('types')->group(function () {
        Route::get('/get', 'App\Http\Controllers\InstitutionTypeController@index')->name('types.index');
        Route::post('/store', 'App\Http\Controllers\InstitutionTypeController@store')->name('types.store');
        Route::patch('/update/{type}', 'App\Http\Controllers\InstitutionTypeController@update')->name('types.update');
        Route::delete('/delete/{type}', 'App\Http\Controllers\InstitutionTypeController@destroy')->name('types.destroy');
        Route::post('/import', 'App\Http\Controllers\InstitutionTypeController@import')->name('types.import');
    });
    Route::prefix('groups')->group(function () {
        Route::get('/get', 'App\Http\Controllers\InstitutionGroupController@index')->name('groups.index');
        Route::post('/store', 'App\Http\Controllers\InstitutionGroupController@store')->name('groups.store');
        Route::patch('/update/{group}', 'App\Http\Controllers\InstitutionGroupController@update')->name('groups.update');
        Route::delete('/delete/{group}', 'App\Http\Controllers\InstitutionGroupController@destroy')->name('groups.destroy');
        Route::post('/import', 'App\Http\Controllers\InstitutionGroupController@import')->name('groups.import');
    });
    Route::prefix('platforms')->group(function () {
        Route::get('/get/{role}', 'App\Http\Controllers\GlobalProviderController@index')->name('platforms.index');
        Route::post('/store', 'App\Http\Controllers\GlobalProviderController@store')->name('platforms.store');
        Route::patch('/update/{platform}', 'App\Http\Controllers\GlobalProviderController@update')->name('platforms.update');
        Route::delete('/delete/{platform}', 'App\Http\Controllers\GlobalProviderController@destroy')->name('platforms.destroy');
        Route::get('/exportData', 'App\Http\Controllers\GlobalProviderController@export')->name('platforms.exportData');
        Route::post('/import', 'App\Http\Controllers\GlobalProviderController@import')->name('platforms.import');
        Route::post('/refresh', 'App\Http\Controllers\CounterRegistryController@refresh')->name('platforms.refresh');
    });
    Route::prefix('users')->group(function () {
        Route::get('/get', 'App\Http\Controllers\UserController@index')->name('users.index');
        Route::get('/settings/{user}', 'App\Http\Controllers\UserController@settings')->name('users.settings');
        Route::post('/store', 'App\Http\Controllers\UserController@store')->name('users.store');
        Route::patch('/update/{user}', 'App\Http\Controllers\UserController@update')->name('users.update');
        Route::delete('/delete/{user}', 'App\Http\Controllers\UserController@destroy')->name('users.destroy');
        Route::post('/bulk', 'App\Http\Controllers\UserController@bulk')->name('users.bulk');
        Route::post('/import', 'App\Http\Controllers\UserController@import')->name('users.import');
    });
    Route::prefix('roles')->group(function () {
        Route::get('/get', 'App\Http\Controllers\RoleController@index')->name('roles.index');
        Route::post('/store', 'App\Http\Controllers\RoleController@store')->name('roles.store');
        // Route::patch('/update/{role}', 'App\Http\Controllers\RoleController@update')->name('roles.update');
        Route::delete('/delete/{role}', 'App\Http\Controllers\RoleController@destroy')->name('roles.destroy');
        Route::post('/bulk', 'App\Http\Controllers\RoleController@bulk')->name('roles.bulk');
    });
    Route::prefix('settings')->group(function () {
        Route::get('/get/{type}', 'App\Http\Controllers\GlobalSettingController@index')->name('settings.index');
        Route::post('/store', 'App\Http\Controllers\GlobalSettingController@store')->name('settings.store');
        // Route::post('setSettings', 'App\Http\Controllers\GlobalSettingController@store')->name('setSettings');
        Route::patch('/update/{setting}', 'App\Http\Controllers\GlobalSettingController@update')->name('settings.update');
    });
    Route::prefix('harvests')->group(function () {
        Route::get('/get', 'App\Http\Controllers\HarvestLogController@index')->name('harvests.index');
        Route::post('/getItems', 'App\Http\Controllers\HarvestLogController@getItems')->name('harvests.items');
        Route::get('/options', 'App\Http\Controllers\HarvestLogController@create')->name('harvests.options');
        Route::post('/store', 'App\Http\Controllers\HarvestLogController@store')->name('harvests.store');
        Route::delete('/delete/{harvest}', 'App\Http\Controllers\HarvestLogController@destroy')->name('harvests.destroy');
        Route::post('/bulk', 'App\Http\Controllers\HarvestLogController@bulk')->name('harvests.bulk');
        Route::get('/raw/{harvest}', 'App\Http\Controllers\HarvestLogController@downloadRaw')->name('harvests.downloadRaw');
    });
    Route::prefix('jobs')->group(function () {
        Route::get('/get', 'App\Http\Controllers\HarvestLogController@harvestQueue')->name('jobs.index');
        Route::post('/getItems', 'App\Http\Controllers\HarvestLogController@getItems')->name('jobs.items');
        Route::patch('/update/{harvest}', 'App\Http\Controllers\HarvestLogController@update')->name('jobs.update');
        Route::delete('/delete/{harvest}', 'App\Http\Controllers\HarvestLogController@destroy')->name('jobs.destroy');
        Route::post('/bulk', 'App\Http\Controllers\HarvestLogController@bulk')->name('jobs.bulk');
    });
    Route::prefix('savedreports')->group(function () {
        Route::get('/get', 'App\Http\Controllers\SavedReportController@index')->name('savedreports.index');
        Route::post('/store', 'App\Http\Controllers\SavedReportController@store')->name('savedreports.store');
        Route::delete('/delete/{savedreport}', 'App\Http\Controllers\SavedReportController@destroy')->name('savedreports.destroy');
// This is for new functionality to request a saved report, given ID and optional format
// and get it back via an api call... bypassing the U/I (like from an another application)
        Route::post('/execute/{savedreport}/{format}', 'App\Http\Controllers\SavedReportController@execute')->name('savedreports.execute');
    });
    Route::prefix('reports')->group(function () {
        Route::get('/options', 'App\Http\Controllers\ReportController@create')->name('reports.options');
        Route::post('/usageData', 'App\Http\Controllers\ReportController@usageData')->name('reports.usageData');
        Route::post('/updateColumns', 'App\Http\Controllers\ReportController@updateColumns')->name('reports.updateColumns');
    });
});
