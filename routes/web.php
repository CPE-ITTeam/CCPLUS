<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/', 'SavedReportController@home')->name('index')->middleware(['auth']);
Route::get('/home', 'SavedReportController@home')->name('home')->middleware(['auth']);
// Authentication
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::get('logout', 'Auth\LoginController@logout');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
Route::get('/forgot-password','Auth\ForgotPasswordController@showForgotForm')->name('password.forgot.get');
Route::post('/forgot-password','Auth\ForgotPasswordController@submitForgotForm')->name('password.forgot.post');
Route::get('/reset-password/{consortium}/{token}','Auth\ForgotPasswordController@showResetForm')
     ->name('password.reset.get');
Route::post('/reset-password','Auth\ForgotPasswordController@submitResetForm')->name('password.reset.post');
// Users and Roles
Route::resource('/roles', 'RoleController')->middleware(['auth']);
Route::resource('/users', 'UserController')->middleware(['auth','cache.headers:no_store']);
Route::post('/users/import', 'UserController@import')->name('users.import');
Route::get('/users-export', 'UserController@export')->name('users.export');
// Institutions
Route::resource('institutions', 'InstitutionController')->middleware(['auth','cache.headers:no_store']);
Route::prefix('institution')->name('institution.')->group(function () {
    Route::resource('groups', 'InstitutionGroupController')->middleware(['auth','cache.headers:no_store']);
    Route::post('/groups/import', 'InstitutionGroupController@import')->name('groups.import')->middleware(['auth']);
    Route::get('/groups/export/{type}', 'InstitutionGroupController@export')->name('groups.export')->middleware(['auth']);
    Route::resource('types', 'InstitutionTypeController')->middleware(['auth','cache.headers:no_store']);
    Route::get('/types/export/{type}', 'InstitutionTypeController@export')->name('types.export');
    Route::post('/types/import', 'InstitutionTypeController@import')->name('types.import');
});
Route::get('/available-institutions', 'HarvestLogController@availableInstitutions')->middleware(['auth']);
Route::post('/institutions/import', 'InstitutionController@import')->name('institutions.import');
Route::get('/institutions-export', 'InstitutionController@export')->name('institutions.export');
Route::post('extend-institution-group', 'InstitutionGroupController@extend')->name('groups.extend')
     ->middleware(['auth','role:Admin,Manager']);
// Providers
Route::delete('providers/customDestroy/{globalProvID}/{instProvID}', 'ProviderController@customDestroy');
Route::resource('/providers', 'ProviderController')->middleware(['auth','cache.headers:no_store']);
Route::post('/providers/connect', 'ProviderController@connect')->name('providers.connect')
     ->middleware(['auth','role:Admin,Manager']);
Route::get('/available-providers', 'HarvestLogController@availableProviders')->middleware(['auth']);
Route::post('/providers/import', 'ProviderController@import')->name('providers.import');
Route::get('/providers-export', 'ProviderController@export')->name('providers.export');
Route::post('/update-report-state', 'ProviderController@updateReportState')->name('providers.reportState')
     ->middleware(['auth','role:Admin,Manager']);
// Sushi and Harvests
Route::resource('/harvests', 'HarvestLogController')->middleware(['auth','cache.headers:no_store']);
Route::get('/harvests/{id}/raw', 'HarvestLogController@downloadRaw')->name('harvests.download')
     ->middleware(['auth','role:Admin,Manager']);
Route::get('/harvest-queue', 'HarvestLogController@harvestQueue')->name('harvests.jobs')->middleware(['auth','role:Admin,Manager']);
Route::post('/bulk-harvest-delete', 'HarvestLogController@bulkDestroy')->name('harvests.bulkDestroy')
     ->middleware(['auth','role:Admin,Manager']);
Route::post('/update-harvest-status', 'HarvestLogController@updateStatus')->name('harvests.changeStatus')
     ->middleware(['auth','role:Admin,Manager']);
Route::resource('/sushisettings', 'SushiSettingController')->middleware(['auth','role:Admin,Manager','cache.headers:no_store']);
Route::get('/sushisettings-refresh', 'SushiSettingController@refresh')->name('sushisettings.refresh')->middleware(['auth']);
Route::post('/sushisettings-test', 'SushiSettingController@test')->name('sushisettings.test')
     ->middleware(['auth','role:Admin,Manager']);
Route::post('/sushisettings/import', 'SushiSettingController@import')->name('sushisettings.import');
Route::get('/sushi-export', 'SushiSettingController@export')->name('sushisettings.export');
Route::get('/sushi-audit', 'SushiSettingController@audit')->name('sushisettings.audit');
// Reports
Route::get('/reports', 'SavedReportController@index')->name('reports.index')->middleware('auth');
Route::get('/reports/create', 'ReportController@create')->name('reports.create')->middleware('auth');
Route::get('/reports/preview', 'ReportController@preview')->name('reports.preview')->middleware('auth');
Route::get('/reports/{id}', 'ReportController@show')->name('reports.show')->middleware('auth');
Route::get('/reports-available', 'ReportController@getAvailable')->name('reports.available')->middleware(['auth']);
Route::get('/usage-report-data', 'ReportController@getReportData')->name('reports.getData')->middleware(['auth']);
Route::post('/update-report-columns', 'ReportController@updateReportColumns')->name('reports.updateCols')->middleware(['auth']);
Route::resource('/my-reports', 'SavedReportController')->middleware(['auth']);
Route::post('/save-report-config', 'SavedReportController@saveReportConfig')->name('my-reports.save')->middleware(['auth']);
// Alerts
Route::get('/alerts', 'AlertController@index')->name('alerts')->middleware(['auth','role:Admin']);
Route::resource('/systemalerts', 'SystemAlertController')->middleware(['auth']);
Route::post('/update-alert-status', 'AlertController@updateStatus')->middleware(['auth','role:Admin,Manager']);
Route::post('/update-system-alert', 'AlertController@updateSysAlert')->middleware(['auth','role:Admin,Manager']);
Route::post('/alert-dash-refresh', 'AlertController@dashRefresh')->middleware('auth');
Route::resource('/alertsettings', 'AlertSettingController')
     ->middleware(['auth','role:Admin,Manager','cache.headers:no_store']);
Route::post('/alertsettings-fields-refresh', 'AlertSettingController@fieldsRefresh')->middleware(['auth','role:Admin,Manager']);
// Global admin routes
Route::get('/admin', 'AdminController@index')->name('adminHome')->middleware(['auth','role:Admin,Manager']);
Route::get('/server/home', 'GlobalAdminController@index')->name('server.home')->middleware('auth','role:ServerAdmin');
Route::resource('/server/config', 'GlobalSettingController')->middleware('auth','role:ServerAdmin');
Route::resource('/global/providers', 'GlobalProviderController', ['as' => 'global'])->middleware('auth','role:ServerAdmin');
Route::post('/global/providers/registry-refresh', 'CounterRegistryController@registryRefresh')->name('counterRegistry.refresh');
Route::post('/global/providers/import', 'GlobalProviderController@import')->name('global.providers.import');
Route::get('/global-providers-export', 'GlobalProviderController@export')->name('global.providers.export');
Route::resource('/consortia', 'ConsortiumController')->middleware('auth','role:ServerAdmin');
Route::get('/change-instance/{key}/{page}', 'GlobalAdminController@changeInstance')->name('global.changeInstance')
     ->middleware('auth','role:ServerAdmin');
Route::get('/consoadmin', 'AdminController@index')->name('admin.home')->middleware('auth','role:Admin');

Route::get('/iptest', 'GlobalAdminController@ipTest')->name('ipTest')->middleware(['auth','role:Admin,Manager']);
