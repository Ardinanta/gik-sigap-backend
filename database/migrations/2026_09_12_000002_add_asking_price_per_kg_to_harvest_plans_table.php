<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('harvest_plans', function (Blueprint $table) {
            $table->decimal('asking_price_per_kg', 14, 2)->nullable()->after('estimated_volume_kg');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE harvest_plans ADD CONSTRAINT chk_harvest_plans_asking_price CHECK (asking_price_per_kg IS NULL OR asking_price_per_kg > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE harvest_plans DROP CONSTRAINT IF EXISTS chk_harvest_plans_asking_price');
        }

        Schema::table('harvest_plans', function (Blueprint $table) {
            $table->dropColumn('asking_price_per_kg');
        });
    }
};
