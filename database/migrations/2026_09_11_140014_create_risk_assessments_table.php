<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_planned_volume_kg', 12, 2);
            $table->decimal('threshold_volume_snapshot', 12, 2);
            $table->decimal('warning_ratio_snapshot', 5, 4);
            $table->string('risk_level', 30)->index();
            $table->string('coordination_status', 30)->default('uncoordinated');
            $table->foreignId('coordinated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('coordinated_at')->nullable();
            $table->string('algorithm_version', 30)->default('v1');
            $table->timestampTz('calculated_at')->useCurrent()->index();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['location_id', 'period_start', 'period_end']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE risk_assessments ADD CONSTRAINT chk_risk_assessments_period CHECK (period_end >= period_start)');
            DB::statement('ALTER TABLE risk_assessments ADD CONSTRAINT chk_risk_assessments_volume CHECK (total_planned_volume_kg >= 0)');
            DB::statement('ALTER TABLE risk_assessments ADD CONSTRAINT chk_risk_assessments_threshold CHECK (threshold_volume_snapshot > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
