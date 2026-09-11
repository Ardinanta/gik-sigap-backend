<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harvest_plan_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_demand_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('reserved_volume_kg', 12, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservations ADD CONSTRAINT chk_reservations_volume CHECK (reserved_volume_kg > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
