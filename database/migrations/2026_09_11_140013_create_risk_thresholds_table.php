<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->string('period_type', 30)->default('weekly');
            $table->decimal('threshold_volume_kg', 12, 2);
            $table->decimal('warning_ratio', 5, 4)->default(0.8000);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('source_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['location_id', 'commodity_id', 'effective_from', 'effective_until'], 'idx_risk_thresholds_effective');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE risk_thresholds ADD CONSTRAINT chk_risk_thresholds_volume CHECK (threshold_volume_kg > 0)');
            DB::statement('ALTER TABLE risk_thresholds ADD CONSTRAINT chk_risk_thresholds_warning_ratio CHECK (warning_ratio > 0 AND warning_ratio <= 1)');
            DB::statement('ALTER TABLE risk_thresholds ADD CONSTRAINT chk_risk_thresholds_date CHECK (effective_until IS NULL OR effective_until >= effective_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_thresholds');
    }
};
