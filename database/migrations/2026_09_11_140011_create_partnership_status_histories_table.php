<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->timestampTz('changed_at')->useCurrent();

            $table->index(['partnership_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_status_histories');
    }
};
