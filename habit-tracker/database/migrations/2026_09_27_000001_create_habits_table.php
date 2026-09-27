<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('frequency_type', ['daily', 'weekly'])->default('daily');
            $table->unsignedTinyInteger('weekly_target')->nullable();
            $table->string('color', 7)->default('#10b981');
            // Fecha local desde la que se evalúa el hábito (no se usa created_at
            // porque está en UTC y porque así se puede ajustar a mano).
            $table->date('start_date');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
