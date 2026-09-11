<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function (): array {
        return [
            'success' => true,
            'message' => 'API SIGAP siap digunakan.',
        ];
    })->name('api.v1.health');

    Route::get('/auth/me', function () {
        return request()->user();
    })->middleware('auth:sanctum')->name('api.v1.auth.me');
});
