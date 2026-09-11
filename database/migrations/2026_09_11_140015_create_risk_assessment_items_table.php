<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('harvest_plan_id')->constrained()->restrictOnDelete();
            $table->decimal('volume_snapshot_kg', 12, 2);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['risk_assessment_id', 'harvest_plan_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE risk_assessment_items ADD CONSTRAINT chk_risk_items_volume CHECK (volume_snapshot_kg > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessment_items');
    }
};
