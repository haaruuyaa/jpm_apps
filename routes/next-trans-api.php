<?php

use App\Http\Controllers\BCASnapController;
use App\Http\Controllers\NextTransController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BCA API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register BCA API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// next trans
Route::get('balance',[NextTransController::class, 'balance']);
//Route::get('get-bank',[NextTransController::class, 'bankList']);
//Route::get('get-country',[NextTransController::class, 'countryList']);

// custom
Route::get('cek-rek',[NextTransController::class, 'checkRekening']);
Route::post('transfer',[NextTransController::class, 'transfer']);
Route::get('cek-status',[NextTransController::class, 'checkStatus']);
Route::get('cek-status',[NextTransController::class, 'checkStatus']);
Route::get('cek-kode-bank',[NextTransController::class, 'checkKodeBank']);
Route::post('callback', [NextTransController::class, 'callback']);




