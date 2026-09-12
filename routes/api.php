<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\BuyerDemandController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\FishSizeController;
use App\Http\Controllers\Api\V1\MatchingController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function (): array {
        return [
            'success' => true,
            'message' => 'API SIGAP siap digunakan.',
        ];
    })->name('api.v1.health');

    Route::get('/locations', [LocationController::class, 'index'])
        ->name('api.v1.locations.index');
    Route::get('/fish-sizes', [FishSizeController::class, 'index'])
        ->name('api.v1.fish-sizes.index');

    Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');
        Route::post('/forgot-password', [PasswordController::class, 'forgotPassword'])
            ->middleware('throttle:password-reset')
            ->name('forgot-password');
        Route::post('/reset-password', [PasswordController::class, 'resetPassword'])
            ->middleware('throttle:password-reset')
            ->name('reset-password');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::apiResource('buyer-demands', BuyerDemandController::class)
            ->only(['index', 'store', 'destroy']);
        Route::apiResource('catalog', CatalogController::class)
            ->parameters(['catalog' => 'harvestPlan'])
            ->only(['index', 'show']);
        Route::get('/buyer-demands/{buyerDemand}/matches', [MatchingController::class, 'index'])
            ->name('api.v1.buyer-demands.matches.index');
        Route::post('/buyer-demands/{buyerDemand}/matches/generate', [MatchingController::class, 'generate'])
            ->name('api.v1.buyer-demands.matches.generate');
        Route::get('/matches/{matchResult}', [MatchingController::class, 'show'])
            ->name('api.v1.matches.show');
        Route::post('/harvest-plans/{harvestPlan}/reservations', [ReservationController::class, 'store'])
            ->name('api.v1.harvest-plans.reservations.store');
    });
});
