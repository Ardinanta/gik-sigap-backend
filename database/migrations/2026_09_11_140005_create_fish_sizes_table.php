<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fish_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->decimal('min_weight_gram', 10, 2)->nullable();
            $table->decimal('max_weight_gram', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['commodity_id', 'code']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fish_sizes ADD CONSTRAINT chk_fish_sizes_weight CHECK (min_weight_gram IS NULL OR max_weight_gram IS NULL OR max_weight_gram >= min_weight_gram)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fish_sizes');
    }
};
