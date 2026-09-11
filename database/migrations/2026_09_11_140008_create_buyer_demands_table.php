<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_location_id')->nullable()->index()->constrained('locations')->nullOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('fish_size_id')->constrained()->restrictOnDelete();
            $table->decimal('required_volume_kg', 12, 2);
            $table->date('need_start_date');
            $table->date('need_end_date');
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->softDeletesTz();

            $table->index(['need_start_date', 'need_end_date']);
            $table->index('fish_size_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE buyer_demands ADD CONSTRAINT chk_buyer_demands_volume CHECK (required_volume_kg > 0)');
            DB::statement('ALTER TABLE buyer_demands ADD CONSTRAINT chk_buyer_demands_date CHECK (need_end_date >= need_start_date)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_demands');
    }
};
