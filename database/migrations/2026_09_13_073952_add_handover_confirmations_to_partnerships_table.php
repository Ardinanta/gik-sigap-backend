<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table): void {
            $table->decimal('seller_weight_kg', 12, 2)->nullable();
            $table->decimal('buyer_weight_kg', 12, 2)->nullable();
            $table->timestampTz('seller_confirmed_at')->nullable();
            $table->timestampTz('buyer_confirmed_at')->nullable();
            $table->unsignedInteger('handover_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('partnerships', function (Blueprint $table): void {
            $table->dropColumn(['seller_weight_kg', 'buyer_weight_kg', 'seller_confirmed_at', 'buyer_confirmed_at', 'handover_version']);
        });
    }
};
