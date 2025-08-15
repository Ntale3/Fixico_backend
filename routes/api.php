<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/oauth-login', [AuthController::class, 'oauthLogin']);


    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::delete('/account', [AuthController::class, 'deleteAccount']);
    });
});





Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{blog}', [BlogController::class, 'show']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/blogs', [BlogController::class, 'store']);
    Route::put('/blogs/{blog}', [BlogController::class, 'update']);
    Route::post('/blogs/{blog}/comments', [BlogController::class, 'storeComment']);
    Route::get('/my-blogs', [BlogController::class, 'myBlogs']);
    Route::delete('/blogs/{blog}', [BlogController::class, 'destroy']);
});

