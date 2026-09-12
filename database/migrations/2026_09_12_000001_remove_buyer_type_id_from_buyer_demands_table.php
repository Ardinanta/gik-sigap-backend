<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_demands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_demands', function (Blueprint $table) {
            $table->foreignId('buyer_type_id')
                ->nullable()
                ->after('buyer_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }
};
