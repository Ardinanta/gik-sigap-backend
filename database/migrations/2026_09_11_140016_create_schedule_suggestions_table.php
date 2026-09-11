<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('harvest_plan_id')->index()->constrained()->cascadeOnDelete();
            $table->date('original_date');
            $table->date('suggested_start_date');
            $table->date('suggested_end_date');
            $table->text('reason');
            $table->string('status', 30)->default('pending')->index();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_suggestions ADD CONSTRAINT chk_schedule_suggestions_date CHECK (suggested_end_date >= suggested_start_date)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_suggestions');
    }
};
