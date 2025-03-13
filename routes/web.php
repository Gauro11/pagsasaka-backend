<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

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

Route::get('/', function () {
    return view('welcome');
});

// PayMongo Payment Routes
Route::post('/pay', [PaymentController::class, 'pay']);
Route::get('/success', [PaymentController::class, 'success']);
Route::get('/link-pay', [PaymentController::class, 'linkPay']);
Route::get('/link-status/{linkid}', [PaymentController::class, 'linkStatus']);
Route::post('/refund', [PaymentController::class, 'refund']);
Route::get('/refund-status/{id}', [PaymentController::class, 'refundStatus']);