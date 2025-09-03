<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/**
 * auth Routes
 * These routes are for user authentication, registration, and profile management.
 * They include public routes for registration and login, and authenticated routes for profile management.
 * Authenticated routes require a valid Sanctum token.
 */
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

/**
 * Blog Routes
 * These routes are for managing blogs and comments.
 * Public routes for viewing blogs, authenticated routes for creating and managing blogs.
 * Public routes for viewing blogs, authenticated routes for creating and managing blogs.
 * Public routes for viewing blogs, authenticated routes for creating and managing blogs.
 */
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
