<?php

use App\Http\Controllers\BCASnapController;
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

Route::get('balance',[BCASnapController::class, 'balance']);
Route::get('account',[BCASnapController::class, 'accountInquiry']);
Route::get('statement',[BCASnapController::class, 'bankStatement']);
Route::post('transfer-ke-va',[BCASnapController::class, 'transferToVA']);
Route::post('transfer-ke-bca',[BCASnapController::class, 'transferToBca']);
Route::post('payment-ke-va',[BCASnapController::class, 'paymentToVA']);
Route::get('cek-status',[BCASnapController::class, 'transferInquiryBCA']);
Route::get('cek-status-va',[BCASnapController::class, 'transferInquiryVABCA']);
Route::get('cek-status-payment-va',[BCASnapController::class, 'paymentInquiryVABCA']);
