<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/',function(){
return view('welcome');
});


Route::get('/pay',action: [PaymentController::class,'requestToPay']);
Route::get('/status/{id}',action: [PaymentController::class,'status']);

