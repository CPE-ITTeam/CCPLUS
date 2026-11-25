<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('consoList', 'App\Http\Controllers\ConsortiumController@index')->name('consoList');
Route::post('forgotPass', 'App\Http\Controllers\Auth\ForgotPasswordController@submitForgotForm')
    ->name('forgotPass');
Route::post('login', 'App\Http\Controllers\Auth\LoginController@login');
Route::get('logout', 'App\Http\Controllers\Auth\LoginController@logout');
Route::post('resetPass/', 'App\Http\Controllers\Auth\ForgotPasswordController@submitResetForm')
    ->name('resetPass');

// Routes requiring authentication
Route::middleware('ccplusAuth')->group(function () {
    Route::post('/updateSession', 'App\Http\Controllers\SessionController@update')->name('updateSession');
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Routes grouped alphabetically by prefix
    Route::prefix('audit')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\CredentialController@bulk')->name('audit.bulk');
        Route::get('/get', 'App\Http\Controllers\CredentialController@audit')->name('audit.index');
        Route::post('/getItems', 'App\Http\Controllers\CredentialController@auditItems')->name('audit.items');
        Route::patch('/update/{credential}', 'App\Http\Controllers\CredentialController@setvalidated')->name('audit.update');
    });

    Route::prefix('connections')->group(function () {
        Route::post('/access', 'App\Http\Controllers\ConnectionController@access')->name('connections.reportAccess');
        Route::delete('/delete/{connection}', 'App\Http\Controllers\ConnectionController@destroy')->name('connections.destroy');
        Route::get('/get', 'App\Http\Controllers\ConnectionController@index')->name('connections.index');
        Route::post('/import', 'App\Http\Controllers\ConnectionController@import')->name('connections.import');
        Route::post('/store', 'App\Http\Controllers\ConnectionController@store')->name('connections.store');
        Route::patch('/update/{connection}', 'App\Http\Controllers\ConnectionController@update')->name('connections.update');
    });

    Route::prefix('consortia')->group(function () {
        Route::get('/current', 'App\Http\Controllers\ConsortiumController@current')->name('consortia.current');
        Route::patch('/current', 'App\Http\Controllers\ConsortiumController@updateCurrent')->name('consortia.updateCurrent');
        Route::get('/get', 'App\Http\Controllers\ConsortiumController@index')->name('consortia.index');
        Route::post('/store', 'App\Http\Controllers\ConsortiumController@store')->name('consortia.store');
        Route::patch('/update/{consortium}', 'App\Http\Controllers\ConsortiumController@update')->name('consortia.update');
    });

    Route::prefix('credentials')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\CredentialController@bulk')->name('credentials.bulk');
        Route::delete('/delete/{credential}', 'App\Http\Controllers\CredentialController@destroy')->name('credentials.destroy');
        Route::get('/get', 'App\Http\Controllers\CredentialController@index')->name('credentials.index');
        Route::post('/import', 'App\Http\Controllers\CredentialController@import')->name('credentials.import');
        Route::post('/store', 'App\Http\Controllers\CredentialController@store')->name('credentials.store');
        Route::post('/test', 'App\Http\Controllers\CredentialController@test')->name('credentials.test');
        Route::post('/unset', 'App\Http\Controllers\CredentialController@unset')->name('credentials.unset');
        Route::patch('/update/{credential}', 'App\Http\Controllers\CredentialController@update')->name('credentials.update');
    });

    // API routes to expose global database tables
    Route::prefix('global')->group(function () {
        Route::get('/databases', 'App\Http\Controllers\GlobalDataController@databases')->name('global.databases');
        Route::get('/datahosts', 'App\Http\Controllers\GlobalDataController@datahosts')->name('global.datahosts');
        Route::get('/errors', 'App\Http\Controllers\GlobalDataController@errors')->name('global.errors');
        Route::get('/items', 'App\Http\Controllers\GlobalDataController@items')->name('global.items');
        Route::get('/platforms', 'App\Http\Controllers\GlobalDataController@platforms')->name('global.platforms');
        Route::get('/providers', 'App\Http\Controllers\GlobalProviderController@apiIndex')->name('global.providers');
        Route::get('/publishers', 'App\Http\Controllers\GlobalDataController@publishers')->name('global.publishers');
        Route::get('/registries', 'App\Http\Controllers\CounterRegistryController@index')->name('global.registries');
        Route::get('/registries/cancel', 'App\Http\Controllers\CounterRegistryController@cancel')->name('global.registries.cancel');
        Route::get('/registries/progress', 'App\Http\Controllers\CounterRegistryController@progress')->name('global.registries.progress');
        Route::get('/titles', 'App\Http\Controllers\GlobalDataController@titles')->name('global.titles');
    });

    Route::prefix('groups')->group(function () {
        Route::delete('/delete/{group}', 'App\Http\Controllers\InstitutionGroupController@destroy')->name('groups.destroy');
        Route::get('/get', 'App\Http\Controllers\InstitutionGroupController@index')->name('groups.index');
        Route::post('/import', 'App\Http\Controllers\InstitutionGroupController@import')->name('groups.import');
        Route::post('/store', 'App\Http\Controllers\InstitutionGroupController@store')->name('groups.store');
        Route::patch('/update/{group}', 'App\Http\Controllers\InstitutionGroupController@update')->name('groups.update');
    });

    Route::prefix('harvests')->group(function () {
        Route::get('/bad-credentials/get', 'App\Http\Controllers\HarvestLogController@badCredentials')->name('harvests.badCredentials');
        Route::post('/bulk', 'App\Http\Controllers\HarvestLogController@bulkAction')->name('harvests.bulk');
        Route::delete('/delete/{harvest}', 'App\Http\Controllers\HarvestLogController@destroy')->name('harvests.destroy');
        Route::get('/get', 'App\Http\Controllers\HarvestLogController@index')->name('harvests.index');
        Route::post('/getItems', 'App\Http\Controllers\HarvestLogController@index')->name('harvests.items');
        Route::get('/options', 'App\Http\Controllers\HarvestLogController@create')->name('harvests.options');
        Route::get('/raw/{harvest}', 'App\Http\Controllers\HarvestLogController@downloadRaw')->name('harvests.downloadRaw');
        Route::post('/store', 'App\Http\Controllers\HarvestLogController@store')->name('harvests.store');
    });

    Route::prefix('institutions')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\InstitutionController@bulk')->name('institutions.bulk');
        Route::delete('/delete/{institution}', 'App\Http\Controllers\InstitutionController@destroy')->name('institutions.destroy');
        Route::get('/get/{role}', 'App\Http\Controllers\InstitutionController@index')->name('institutions.index');
        Route::post('/import', 'App\Http\Controllers\InstitutionController@import')->name('institutions.import');
        Route::post('/store', 'App\Http\Controllers\InstitutionController@store')->name('institutions.store');
        Route::patch('/update/{institution}', 'App\Http\Controllers\InstitutionController@update')->name('institutions.update');
    });

    Route::prefix('jobs')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\HarvestLogController@bulkAction')->name('jobs.bulk');
        Route::delete('/delete/{harvest}', 'App\Http\Controllers\HarvestLogController@destroy')->name('jobs.destroy');
        Route::get('/get', 'App\Http\Controllers\HarvestLogController@harvestQueue')->name('jobs.index');
        Route::post('/getItems', 'App\Http\Controllers\HarvestLogController@harvestQueue')->name('jobs.items');
        Route::patch('/update/{harvest}', 'App\Http\Controllers\HarvestLogController@update')->name('jobs.update');
    });

    Route::prefix('platforms')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\GlobalProviderController@bulk')->name('platforms.bulk');
        Route::delete('/delete/{platform}', 'App\Http\Controllers\GlobalProviderController@destroy')->name('platforms.destroy');
        Route::get('/exportData', 'App\Http\Controllers\GlobalProviderController@export')->name('platforms.exportData');
        Route::get('/get', 'App\Http\Controllers\GlobalProviderController@index')->name('platforms.index');
        Route::post('/import', 'App\Http\Controllers\GlobalProviderController@import')->name('platforms.import');
        Route::post('/refresh', 'App\Http\Controllers\CounterRegistryController@refresh')->name('platforms.refresh');
        Route::post('/store', 'App\Http\Controllers\GlobalProviderController@store')->name('platforms.store');
        Route::patch('/update/{platform}', 'App\Http\Controllers\GlobalProviderController@update')->name('platforms.update');
    });

    Route::prefix('reports')->group(function () {
        Route::get('/options', 'App\Http\Controllers\ReportController@create')->name('reports.options');
        Route::post('/updateColumns', 'App\Http\Controllers\ReportController@updateColumns')->name('reports.updateColumns');
        Route::post('/usageData', 'App\Http\Controllers\ReportController@usageData')->name('reports.usageData');
    });
    Route::prefix('roles')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\RoleController@bulk')->name('roles.bulk');
        Route::delete('/delete/{role}', 'App\Http\Controllers\RoleController@destroy')->name('roles.destroy');
        Route::get('/get', 'App\Http\Controllers\RoleController@index')->name('roles.index');
        Route::post('/store', 'App\Http\Controllers\RoleController@store')->name('roles.store');
        Route::patch('/update/{id}', 'App\Http\Controllers\RoleController@update')->name('roles.update');
    });


    Route::prefix('savedreports')->group(function () {
        Route::delete('/delete/{id}', 'App\Http\Controllers\SavedReportController@destroy')->name('savedreports.destroy');
        Route::get('/execute/{id}', 'App\Http\Controllers\SavedReportController@execute')->name('savedreports.execute');
        Route::get('/get', 'App\Http\Controllers\SavedReportController@index')->name('savedreports.index');
        Route::post('/store', 'App\Http\Controllers\SavedReportController@store')->name('savedreports.store');
    });

    Route::prefix('settings')->group(function () {
        Route::get('/get/{type}', 'App\Http\Controllers\GlobalSettingController@index')->name('settings.index');
        Route::post('/store', 'App\Http\Controllers\GlobalSettingController@store')->name('settings.store');
        Route::patch('/update/{setting}', 'App\Http\Controllers\GlobalSettingController@update')->name('settings.update');
        Route::get('/workers', 'App\Http\Controllers\GlobalSettingController@workerStatus')->name('settings.workers');
        Route::post('/workers/run', 'App\Http\Controllers\GlobalSettingController@runWorker')->name('settings.workers.run');
    });

    Route::prefix('tokens')->group(function () {
        Route::delete('/destroy', 'App\Http\Controllers\Auth\TokenController@destroy')->name('tokens.destroy');
        Route::get('/get', 'App\Http\Controllers\Auth\TokenController@index')->name('tokens.index');
        Route::get('/show/{user}', 'App\Http\Controllers\Auth\TokenController@show')->name('tokens.show');
        Route::post('/store', 'App\Http\Controllers\Auth\TokenController@store')->name('tokens.store');
    });

    Route::prefix('types')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\InstitutionTypeController@bulk')->name('types.bulk');
        Route::delete('/delete/{type}', 'App\Http\Controllers\InstitutionTypeController@destroy')->name('types.destroy');
        Route::get('/get', 'App\Http\Controllers\InstitutionTypeController@index')->name('types.index');
        Route::post('/import', 'App\Http\Controllers\InstitutionTypeController@import')->name('types.import');
        Route::post('/store', 'App\Http\Controllers\InstitutionTypeController@store')->name('types.store');
        Route::patch('/update/{type}', 'App\Http\Controllers\InstitutionTypeController@update')->name('types.update');
    });

    Route::prefix('users')->group(function () {
        Route::post('/bulk', 'App\Http\Controllers\UserController@bulk')->name('users.bulk');
        Route::delete('/delete/{user}', 'App\Http\Controllers\UserController@destroy')->name('users.destroy');
        Route::get('/get', 'App\Http\Controllers\UserController@index')->name('users.index');
        Route::post('/import', 'App\Http\Controllers\UserController@import')->name('users.import');
        Route::get('/settings/{user}', 'App\Http\Controllers\UserController@settings')->name('users.settings');
        Route::post('/store', 'App\Http\Controllers\UserController@store')->name('users.store');
        Route::patch('/update/{user}', 'App\Http\Controllers\UserController@update')->name('users.update');
    });
});
