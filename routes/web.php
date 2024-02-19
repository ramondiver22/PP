<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

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
Auth::routes();

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('index');
// /Route::get('/provider/{provider_slug}', [App\Http\Controllers\HomeController::class, 'groupByProvider'])->name('groupByProvider');

Route::get('/play/{game}', [App\Http\Controllers\HomeController::class, 'iframe']);

Route::get('/launcher', [App\Http\Controllers\SlotmachineController::class, 'launcher'])->name('launcher');
Route::get('/cachedGamelist', [App\Models\Gamelist::class, 'cachedGamelist'])->name('cachedGamelist');

//Route::get('/dddd', [App\Http\Controllers\GameUtillityFunctions::class, 'retrieveGamesTollgate'])->name('retrieveGamesTollgate');


//Route::get('/static_pragmatic/{game_id}/desktop/game/{file}', [App\Http\Controllers\GameUtillityFunctions::class, 'getJSExternal'])->name('getJSExternal');
//Route::get('/static_pragmatic/{game_id}/desktop/client/{file}', [App\Http\Controllers\GameUtillityFunctions::class, 'getJSExternal'])->name('getJSExternal');
//Route::get('/static_pragmatic/{game_id}/desktop/{file}', [App\Http\Controllers\GameUtillityFunctions::class, 'getJSExternal'])->name('getJSExternal');

Route::any('/gs2c/saveSettings.do', [App\Http\Controllers\GameTunnelAPI::class, 'pragmaticplaySettingsStateCurl'])->name('savesettings');
Route::any('/gs2c/reloadBalance.do', [App\Http\Controllers\GameTunnelAPI::class, 'pragmaticplayBalanceOnly'])->name('reloadbalance');
