<?php

use App\Models\BuyerDemand;
use App\Models\HarvestPlan;
use App\Models\MatchResult;
use App\Models\Partnership;
use App\Models\Reservation;
use App\Models\RiskAssessment;
use App\Models\ScheduleSuggestion;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

it('seeds a complete demo dataset for every application role', function () {
    $this->travelTo('2026-09-15 10:00:00');

    $this->seed(DatabaseSeeder::class);

    $farmer = User::query()->where('email', 'petambak@sigap.test')->firstOrFail();
    $buyer = User::query()->where('email', 'pembeli@sigap.test')->firstOrFail();
    $admin = User::query()->where('email', 'admin@sigap.test')->firstOrFail();

    expect(Hash::check('password123', $farmer->password))->toBeTrue();
    expect($farmer->hasRole('farmer'))->toBeTrue();
    expect($buyer->hasRole('buyer'))->toBeTrue();
    expect($admin->hasRole('admin'))->toBeTrue();
    expect(User::query()->count())->toBeGreaterThanOrEqual(8);
    expect(HarvestPlan::query()->distinct()->count('farmer_id'))->toBeGreaterThanOrEqual(4);
    expect(HarvestPlan::query()->distinct()->count('location_id'))->toBe(18);
    expect(HarvestPlan::query()->distinct()->count('fish_size_id'))->toBe(3);
    expect(BuyerDemand::query()->where('status', 'active')->count())->toBeGreaterThanOrEqual(5);
    expect(MatchResult::query()->where('status', 'recommended')->count())->toBeGreaterThan(0);
    expect(Reservation::query()->where('status', 'pending')->count())->toBeGreaterThan(0);
    expect(Partnership::query()->whereIn('status', ['interested', 'discussing', 'matched', 'completed', 'cancelled'])->distinct()->count('status'))->toBe(5);
    expect(Transaction::query()->distinct()->count('transaction_date'))->toBeGreaterThanOrEqual(3);

    expect(RiskAssessment::query()->distinct()->count('location_id'))->toBe(18);
    expect(RiskAssessment::query()->where('risk_level', 'safe')->count())->toBeGreaterThanOrEqual(5);
    expect(RiskAssessment::query()->where('risk_level', 'warning')->count())->toBeGreaterThanOrEqual(5);
    expect(RiskAssessment::query()->where('risk_level', 'high')->count())->toBeGreaterThanOrEqual(5);
    expect(RiskAssessment::query()->where('coordination_status', 'coordinated')->count())->toBe(1);
    expect(ScheduleSuggestion::query()->where('status', 'pending')->count())->toBe(1);
    expect($farmer->unreadNotifications()->count())->toBeGreaterThan(0);
    expect($buyer->notifications()->whereNotNull('read_at')->count())->toBeGreaterThan(0);
});

it('can rerun the complete demo seeder without duplicating identified records', function () {
    $this->travelTo('2026-09-15 10:00:00');

    $this->seed(DatabaseSeeder::class);
    $counts = [
        'users' => User::query()->count(),
        'harvest_plans' => HarvestPlan::query()->count(),
        'buyer_demands' => BuyerDemand::query()->count(),
        'partnerships' => Partnership::query()->count(),
        'reservations' => Reservation::query()->count(),
        'transactions' => Transaction::query()->count(),
        'assessments' => RiskAssessment::query()->count(),
        'suggestions' => ScheduleSuggestion::query()->count(),
        'notifications' => DB::table('notifications')->count(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe($counts['users']);
    expect(HarvestPlan::query()->count())->toBe($counts['harvest_plans']);
    expect(BuyerDemand::query()->count())->toBe($counts['buyer_demands']);
    expect(Partnership::query()->count())->toBe($counts['partnerships']);
    expect(Reservation::query()->count())->toBe($counts['reservations']);
    expect(Transaction::query()->count())->toBe($counts['transactions']);
    expect(RiskAssessment::query()->count())->toBe($counts['assessments']);
    expect(ScheduleSuggestion::query()->count())->toBe($counts['suggestions']);
    expect(DB::table('notifications')->count())->toBe($counts['notifications']);
});
