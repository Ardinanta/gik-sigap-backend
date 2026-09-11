<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->restrictOnDelete();
            $table->foreignId('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('interested')->index();
            $table->decimal('agreed_volume_kg', 12, 2)->nullable();
            $table->decimal('agreed_price_per_kg', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE partnerships ADD CONSTRAINT chk_partnerships_volume CHECK (agreed_volume_kg IS NULL OR agreed_volume_kg > 0)');
            DB::statement('ALTER TABLE partnerships ADD CONSTRAINT chk_partnerships_price CHECK (agreed_price_per_kg IS NULL OR agreed_price_per_kg > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partnerships');
    }
};
