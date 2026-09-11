<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harvest_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_demand_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('match_score', 5, 2)->index();
            $table->decimal('matched_volume_kg', 12, 2)->nullable();
            $table->jsonb('score_breakdown')->nullable();
            $table->string('algorithm_version', 30)->default('v1');
            $table->string('status', 30)->default('recommended')->index();
            $table->timestampTz('matched_at')->useCurrent();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['harvest_plan_id', 'buyer_demand_id', 'algorithm_version']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE matches ADD CONSTRAINT chk_matches_score CHECK (match_score >= 0 AND match_score <= 100)');
            DB::statement('ALTER TABLE matches ADD CONSTRAINT chk_matches_volume CHECK (matched_volume_kg IS NULL OR matched_volume_kg > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
