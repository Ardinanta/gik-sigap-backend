<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('fish_size_id')->constrained()->restrictOnDelete();
            $table->decimal('volume_kg', 12, 2);
            $table->decimal('price_per_kg', 14, 2);
            $table->date('transaction_date')->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['location_id', 'transaction_date']);
            $table->index(['fish_size_id', 'transaction_date']);
            $table->index(['commodity_id', 'transaction_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_volume CHECK (volume_kg > 0)');
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_price CHECK (price_per_kg > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
