<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/pay', action: [PaymentController::class, 'requestToPay']);
Route::get('/status/{id}', action: [PaymentController::class, 'status']);
