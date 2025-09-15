<?php

use Illuminate\Support\Facades\Route;

// Contoh endpoint API publik
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});


