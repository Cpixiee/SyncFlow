<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    // Public authentication routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/create-user', [AuthController::class, 'createUser']);
    
    // Protected routes - require JWT authentication
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});



