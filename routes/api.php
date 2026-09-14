<?php

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminHarvestPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminMasterDataController;
use App\Http\Controllers\Api\V1\Admin\AdminRiskController;
use App\Http\Controllers\Api\V1\Admin\AdminTransactionController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\BuyerDemandController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\FarmerHarvestPlanController;
use App\Http\Controllers\Api\V1\FarmerMatchingController;
use App\Http\Controllers\Api\V1\FarmerPartnershipController;
use App\Http\Controllers\Api\V1\FarmerReservationController;
use App\Http\Controllers\Api\V1\FarmerRiskController;
use App\Http\Controllers\Api\V1\FarmerTransactionController;
use App\Http\Controllers\Api\V1\FishSizeController;
use App\Http\Controllers\Api\V1\HandoverController;
use App\Http\Controllers\Api\V1\MatchingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PartnershipController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\TransactionController;
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
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('api.v1.notifications.index');
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('api.v1.notifications.read-all');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->whereUuid('notification')
            ->name('api.v1.notifications.read');

        Route::prefix('admin')->middleware('admin')->name('api.v1.admin.')->group(function (): void {
            Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
            Route::get('/harvest-plans', AdminHarvestPlanController::class)->name('harvest-plans.index');
            Route::get('/risks', [AdminRiskController::class, 'index'])->name('risks.index');
            Route::get('/risks/{riskAssessment}', [AdminRiskController::class, 'show'])->name('risks.show');
            Route::patch('/risks/{riskAssessment}/coordination', [AdminRiskController::class, 'coordinate'])->name('risks.coordination.update');
            Route::get('/transactions', AdminTransactionController::class)->name('transactions.index');
            Route::get('/master-data', [AdminMasterDataController::class, 'index'])->name('master-data.index');
            Route::post('/locations', [AdminMasterDataController::class, 'storeLocation'])->name('locations.store');
            Route::patch('/locations/{location}', [AdminMasterDataController::class, 'updateLocation'])->name('locations.update');
            Route::post('/fish-sizes', [AdminMasterDataController::class, 'storeFishSize'])->name('fish-sizes.store');
            Route::patch('/fish-sizes/{fishSize}', [AdminMasterDataController::class, 'updateFishSize'])->name('fish-sizes.update');
            Route::post('/risk-thresholds', [AdminMasterDataController::class, 'storeThreshold'])->name('risk-thresholds.store');
            Route::patch('/risk-thresholds/{riskThreshold}', [AdminMasterDataController::class, 'updateThreshold'])->name('risk-thresholds.update');
        });

        Route::prefix('farmer')->name('api.v1.farmer.')->group(function (): void {
            Route::get('/matches', FarmerMatchingController::class)->name('matches.index');
            Route::get('/risks', FarmerRiskController::class)->name('risks.index');
            Route::get('/partnerships', [FarmerPartnershipController::class, 'index'])->name('partnerships.index');
            Route::post('/partnerships/{partnership}/confirmation', [FarmerPartnershipController::class, 'confirm'])->name('partnerships.confirmation.store');
            Route::get('/partnerships/{partnership}', [FarmerPartnershipController::class, 'show'])->name('partnerships.show');
            Route::get('/reservations', [FarmerReservationController::class, 'index'])->name('reservations.index');
            Route::post('/reservations/{reservation}/confirmation', [FarmerReservationController::class, 'confirm'])->name('reservations.confirmation.store');
            Route::get('/transactions', FarmerTransactionController::class)->name('transactions.index');
            Route::apiResource('harvest-plans', FarmerHarvestPlanController::class)
                ->parameters(['harvest-plans' => 'harvestPlan'])
                ->only(['index', 'store', 'show', 'update']);
        });
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
        Route::post('/matches/{matchResult}/partnership', [PartnershipController::class, 'store'])
            ->name('api.v1.matches.partnership.store');
        Route::get('/partnerships', [PartnershipController::class, 'index'])
            ->name('api.v1.partnerships.index');
        Route::get('/partnerships/{partnership}/handover', [HandoverController::class, 'show'])
            ->name('api.v1.partnerships.handover.show');
        Route::post('/partnerships/{partnership}/handover', [HandoverController::class, 'store'])
            ->name('api.v1.partnerships.handover.store');
        Route::get('/partnerships/{partnership}', [PartnershipController::class, 'show'])
            ->name('api.v1.partnerships.show');
        Route::get('/partnerships/{partnership}/history', [PartnershipController::class, 'history'])
            ->name('api.v1.partnerships.history');
        Route::get('/reservations', [ReservationController::class, 'index'])
            ->name('api.v1.reservations.index');
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])
            ->name('api.v1.reservations.destroy');
        Route::post('/harvest-plans/{harvestPlan}/reservations', [ReservationController::class, 'store'])
            ->name('api.v1.harvest-plans.reservations.store');
        Route::get('/transactions', [TransactionController::class, 'index'])
            ->name('api.v1.transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])
            ->name('api.v1.transactions.show');
    });
});
