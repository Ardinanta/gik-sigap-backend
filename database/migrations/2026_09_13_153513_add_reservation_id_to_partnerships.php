<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->unsignedBigInteger('match_id')->nullable()->change();
            $table->foreignId('reservation_id')->nullable()->unique()->constrained('reservations')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
