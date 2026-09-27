<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_priorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('position');
            $table->string('text', 200);
            $table->boolean('completed')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_priorities');
    }
};
