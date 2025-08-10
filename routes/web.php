<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;



Route::get('/pay',action: [PaymentController::class,'requestToPay']);
Route::get('/status/{id}',action: [PaymentController::class,'status']);

