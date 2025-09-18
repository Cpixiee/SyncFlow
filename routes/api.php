<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    // Public authentication routes
    Route::post('/login', [AuthController::class, 'login']);
    
    // Protected routes - require JWT authentication
    Route::middleware('api.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/update-user', [AuthController::class, 'updateUser']);
        
        // SuperAdmin only routes
        Route::middleware('role:superadmin')->group(function () {
            Route::post('/create-user', [AuthController::class, 'createUser']);
            Route::get('/get-user-list', [AuthController::class, 'getUserList']);
            Route::delete('/delete-users', [AuthController::class, 'deleteUsers']);
        });
    });
});



