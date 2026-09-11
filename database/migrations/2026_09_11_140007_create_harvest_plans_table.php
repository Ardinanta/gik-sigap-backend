<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('fish_size_id')->constrained()->restrictOnDelete();
            $table->date('harvest_date');
            $table->decimal('estimated_volume_kg', 12, 2);
            $table->string('pond_name', 150);
            $table->text('pond_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->softDeletesTz();

            $table->index(['location_id', 'harvest_date']);
            $table->index('fish_size_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE harvest_plans ADD CONSTRAINT chk_harvest_plans_volume CHECK (estimated_volume_kg > 0)');
            DB::statement("CREATE INDEX idx_harvest_plans_active_lookup ON harvest_plans (location_id, fish_size_id, harvest_date) WHERE deleted_at IS NULL AND status = 'planned'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_plans');
    }
};
